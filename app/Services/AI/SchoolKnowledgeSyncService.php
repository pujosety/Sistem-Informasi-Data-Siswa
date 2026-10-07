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
            ->where('page_key', 'home')
            ->where('is_enabled', true)
            ->get()
            ->each(function (LandingSection $section) use ($school, &$count): void {
                $content = trim(implode("\n", array_filter([
                    $section->title,
                    $section->subtitle,
                    $section->body,
                    is_array($section->content) ? json_encode($section->content, JSON_UNESCAPED_UNICODE) : null,
                ])));
                if ($content !== '') {
                    $count += $this->upsert($school, 'landing_'.$section->type, $section->id, $section->title ?: ucfirst($section->type), $content, route('home').'#'.$section->type);
                }
            });

        Post::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('kind', Post::KIND_POST)
            ->where('status', Post::PUBLISHED)
            ->where('is_public', true)
            ->get()
            ->each(function (Post $post) use ($school, &$count): void {
                $content = trim(implode("\n", array_filter([$post->title, $post->excerpt, $post->body])));
                $url = $post->source_url ?: route('public.news.show', $post->slug);
                $count += $this->upsert($school, 'post', $post->id, $post->title, $content, $url);
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
