<?php

namespace App\Services;

/**
 * Sanitises author-supplied HTML before it is stored.
 *
 * WHY THIS RUNS AT WRITE TIME AND NOT AT RENDER TIME
 *
 * §58 requires CMS HTML to be sanitised. The only safe place is before it
 * reaches the database: a stored value that has not been cleaned is a stored
 * XSS payload waiting for the first thing that renders it, and that thing may
 * be an export, a feed, an API response or a future theme — none of which
 * anyone reviewed.
 *
 * Escaping at render time is the alternative and it is worse than useless: an
 * editor cannot use a heading or a link, so every article is a wall of plain
 * text, and the first request to "just render it as HTML" becomes the breach.
 *
 * WHY HTMLPurifier
 *
 * It was already a production dependency through phpspreadsheet, and it is an
 * allowlist implementation: elements and attributes it does not recognise are
 * removed rather than known-bad ones being searched for, which is the only
 * approach that survives an attacker who has read the denylist. It is now a
 * direct requirement rather than a transitive one, so dropping phpspreadsheet
 * cannot silently remove CMS sanitisation.
 */
class CmsContentSanitizer
{
    /**
     * Elements refused outright.
     *
     * A denylist rather than the allowlist the first two attempts used, and
     * that change is the whole lesson here. Setting HTML.Allowed REPLACES
     * HTMLPurifier's element set, and with it the per-element attribute
     * definitions that normally permit href — so `<a href="…">` came back as
     * `<a>`. Every link in every article silently stopped working, which is
     * worse than a crash because nothing looks broken.
     *
     * The two directive-shaped ways of fixing that, `Attr.Allowed.<tag>` and
     * `Attr.Allowed`, do not exist; both throw "Cannot set undefined
     * directive" while the CONFIG IS BEING BUILT. Naming an element
     * HTMLPurifier does not have (`mark`, `figure`, `figcaption`) throws the
     * same way, at construction.
     *
     * Leaving the default set and refusing these is the configuration that
     * keeps every safe formatting tag AND every attribute working, and has no
     * allowlist to fall out of step with the library.
     */
    private const FORBIDDEN = 'script,style,iframe,frame,frameset,object,embed,applet,'
        .'form,input,button,select,option,textarea,label,fieldset,legend,datalist,'
        .'base,link,meta,svg,math,template,portal,noscript,marquee,xml,import';

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Plain text needs no filtering, and running it through would wrap it
        // in <p>, which would make every draft look like markup.
        if (! str_contains($html, '<')) {
            return $html;
        }

        return $this->purifier()->purify($html);
    }

    /**
     * Plain text, for previews and meta descriptions.
     *
     * The content of script and style elements is removed along with their
     * tags, not merely untagged.
     *
     * strip_tags() alone removes `<script>` but keeps what was inside it, so
     * "<script>alert(1)</script>" became the text "alert(1)" — which then
     * reached the meta description and every listing card. Nothing executable
     * was rendered, but a payload's text was being published, and on a page
     * whose content is copied into a search index that is not a small thing.
     */
    public function toText(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        // Before strip_tags, so the body of these elements never becomes text.
        $html = preg_replace(
            '#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is',
            '',
            $html
        ) ?? $html;

        // Unclosed forms of the same, for a truncated paste.
        $html = preg_replace('#<(script|style|iframe)\b[^>]*>.*$#is', '', $html) ?? $html;

        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * A short excerpt for listings and meta descriptions.
     *
     * Cut on a word boundary so a card never ends mid-word, and only when
     * there is a reasonable amount of text left — otherwise the result is a
     * single truncated word.
     */
    public function excerpt(?string $html, int $length = 180): ?string
    {
        $text = $this->toText($html);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > (int) ($length * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " ,.;:").'…';
    }

    private function purifier(): \HTMLPurifier
    {
        $config = \HTMLPurifier_Config::createDefault();

        // script and style are refused by the list above; Core.RemoveScript
        // and Core.RemoveStyle do not exist in this version and warn on every
        // call, which in an error-escalating environment is a failure.
        $config->set('HTML.ForbiddenElements', self::FORBIDDEN);

        /*
         * target="_blank" without rel hands the opened page a reference back to
         * this one — a real phishing vector. HTML.TargetBlank is what lets an
         * editor's target survive the filter at all; this is what makes it
         * safe rather than merely possible.
         */
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', false);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);

        /*
         * No serializer cache. The default writes inside the application
         * bundle, which is read-only on a serverless host, and a failed cache
         * write would surface as a 500 on the first save rather than as a
         * filtering problem.
         */
        $config->set('Cache.DefinitionImpl', null);

        return new \HTMLPurifier($config);
    }
}
