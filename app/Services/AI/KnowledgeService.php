<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\Post;
use App\Models\School;
use Illuminate\Support\Str;

class KnowledgeService
{
    /** @return array<int, AiKnowledge> */
    public function search(School $school, string $question, int $limit = 5): array
    {
        $tokens = collect(preg_split('/[^\pL\pN]+/u', Str::lower($question), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $rows = AiKnowledge::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->limit(120)
            ->get();

        return $rows->map(function (AiKnowledge $row) use ($tokens): array {
            $haystack = Str::lower($row->title.' '.$row->content);
            $score = $tokens->sum(fn (string $token) => substr_count($haystack, $token));
            return ['score' => $score, 'row' => $row];
        })->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('row')
            ->values()
            ->all();
    }
}
