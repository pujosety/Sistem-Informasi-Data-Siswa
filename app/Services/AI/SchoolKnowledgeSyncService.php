<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\LandingSection;
use App\Models\Post;
use App\Models\School;
use Illuminate\Support\Str;

class SchoolKnowledgeSyncService
{
    public function sync(School $school): int
    {
        $count = 0;
        $count += $this->upsert($school, 'school', $school->id, $school->name, $this->schoolContent($school), route('public.about'));

        LandingSection::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_enabled', true)
            ->orderBy('page_key')
            ->orderBy('position')
            ->get()
            ->each(function (LandingSection $section) use ($school, &$count): void {
                $content = trim(implode("\n", array_filter([
                    $section->title,
                    $section->subtitle,
                    $section->body,
                    is_array($section->content) ? json_encode($section->content, JSON_UNESCAPED_UNICODE) : null,
                ])));
                if ($content !== '') {
                    $url = $section->page_key === 'home'
                        ? route('home').'#'.$section->type
                        : null;
                    $type = $section->page_key === 'home'
                        ? 'landing_'.$section->type
                        : 'landing_'.$section->page_key.'_'.$section->type;
                    $count += $this->upsert($school, $type, $section->id, $section->title ?: ucfirst($section->type), $content, $url);
                }
            });

        Post::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('kind', [Post::KIND_POST, Post::KIND_PAGE])
            ->where('status', Post::PUBLISHED)
            ->where('is_public', true)
            ->get()
            ->each(function (Post $post) use ($school, &$count): void {
                $content = trim(implode("\n", array_filter([
                    $post->title,
                    $post->excerpt,
                    $post->body,
                    is_array($post->blocks) ? json_encode($post->blocks, JSON_UNESCAPED_UNICODE) : null,
                ])));
                $url = $post->source_url ?: ($post->kind === Post::KIND_PAGE
                    ? route('public.page', $post->slug)
                    : route('public.news.show', $post->slug));
                $type = $post->kind === Post::KIND_PAGE ? 'page' : 'post';
                $count += $this->upsert($school, $type, $post->id, $post->title, $content, $url);
            });

        return $count;
    }

    private function upsert(School $school, string $type, int $sourceId, string $title, string $content, ?string $url): int
    {
        $hash = hash('sha256', $title."\n".$content."\n".$url);
        AiKnowledge::withoutGlobalScopes()->updateOrCreate(
            ['school_id' => $school->id, 'source_type' => $type, 'source_id' => $sourceId],
            ['title' => Str::limit($title, 255, ''), 'content' => $content, 'source_url' => $url, 'content_hash' => $hash, 'is_active' => true]
        );
        return 1;
    }

    private function schoolContent(School $school): string
    {
        $contact = $school->contact ?? [];
        $profile = $school->profile ?? [];
        return trim(implode("\n", array_filter([
            $school->name,
            data_get($profile, 'short'),
            data_get($profile, 'full'),
            data_get($profile, 'values') ? implode(', ', (array) data_get($profile, 'values')) : null,
            data_get($contact, 'address'),
            data_get($contact, 'city'),
            data_get($contact, 'province'),
            data_get($contact, 'phone'),
            data_get($contact, 'email'),
            data_get($contact, 'website'),
        ])));
    }
}
