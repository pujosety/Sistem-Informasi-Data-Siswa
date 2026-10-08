<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\Post;
use App\Models\School;
use Illuminate\Support\Str;

class KnowledgeService
{
    private const STOP_WORDS = [
        'apa', 'apakah', 'bagaimana', 'berapa', 'boleh', 'dan', 'dari', 'di',
        'ini', 'itu', 'ke', 'mana', 'mau', 'pada', 'saja', 'sekolah', 'sebutkan', 'tentang',
        'untuk', 'yang', 'the', 'what', 'where', 'when', 'how', 'is', 'are',
    ];

    private const ALIASES = [
        'ppdb' => ['ppdb', 'penerimaan', 'pendaftaran', 'registrasi'],
        'daftar' => ['daftar', 'pendaftaran', 'registrasi', 'ppdb'],
        'alamat' => ['alamat', 'lokasi', 'kontak', 'telepon', 'email'],
        'program' => ['program', 'kegiatan', 'ekstrakurikuler', 'ekskul'],
        'prestasi' => ['prestasi', 'juara', 'penghargaan', 'kompetisi'],
        'fasilitas' => ['fasilitas', 'sarana', 'prasarana', 'ruang'],
        'guru' => ['guru', 'pengajar', 'pendidik', 'tenaga'],
        'sejarah' => ['sejarah', 'profil', 'tentang', 'berdiri'],
        'jadwal' => ['jadwal', 'agenda', 'kalender', 'waktu'],
    ];

    /** @return array<int, AiKnowledge> */
    public function search(School $school, string $question, int $limit = 8): array
    {
        $normalizedQuestion = trim(Str::lower($question));
        $tokens = collect(preg_split('/[^\pL\pN]+/u', $normalizedQuestion, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->reject(fn (string $token) => in_array($token, self::STOP_WORDS, true))
            ->flatMap(function (string $token): array {
                return array_values(array_unique(array_merge(
                    [$token, $this->stem($token)],
                    self::ALIASES[$token] ?? [],
                )));
            })
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        // The school_id predicate is intentional: no other school's records can
        // enter the candidate set even though global scopes are bypassed here.
        $rows = AiKnowledge::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get();

        return $rows->map(function (AiKnowledge $row) use ($tokens, $normalizedQuestion): array {
            $title = Str::lower((string) $row->title);
            $haystack = Str::lower($row->title.' '.$row->content);
            $score = 0;

            foreach ($tokens as $token) {
                $score += substr_count($title, $token) * 4;
                $score += substr_count($haystack, $token);
            }

            if ($normalizedQuestion !== '' && Str::contains($title, $normalizedQuestion)) {
                $score += 12;
            } elseif ($normalizedQuestion !== '' && Str::contains($haystack, $normalizedQuestion)) {
                $score += 6;
            }

            if ($row->source_type === 'school' && Str::contains($normalizedQuestion, 'nama')) {
                $score += 20;
            }

            return ['score' => $score, 'row' => $row];
        })->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('row')
            ->values()
            ->all();
    }

    private function stem(string $token): ?string
    {
        foreach (['nya', 'lah', 'kah', 'kan', 'an'] as $suffix) {
            if (str_ends_with($token, $suffix) && mb_strlen($token) > mb_strlen($suffix) + 3) {
                return substr($token, 0, -mb_strlen($suffix));
            }
        }

        return null;
    }
}
