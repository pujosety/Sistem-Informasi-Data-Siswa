<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\School;
use App\Services\AI\AiService;
use Illuminate\Support\Str;
use RuntimeException;

class SchoolAssistantService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly KnowledgeService $knowledge,
        private readonly PromptBuilderService $prompts,
    ) {}

    public function answer(School $school, string $message, ?int $conversationId = null, array $requestContext = []): array
    {
        $conversation = $this->conversation($school, $conversationId);
        $matches = $this->knowledge->search($school, $message);
        $smallTalk = $this->smallTalkResponse($school, $message);

        $userMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        if ($smallTalk !== null) {
            $answer = $smallTalk;
            $responseMeta = ['provider' => 'local-personality'];
        } elseif ($matches === []) {
            $answer = $this->notFoundResponse();
            $responseMeta = ['provider' => 'local-fallback'];
        } else {
            $history = $conversation->messages()
                ->latest('created_at')
                ->limit(9)
                ->get()
                ->reverse()
                ->reject(fn (AiMessage $item) => $item->id === $userMessage->id);
            $messages = [['role' => 'system', 'content' => $this->prompts->system($school, $matches, $requestContext)]];
            foreach ($history as $item) {
                $messages[] = ['role' => $item->role, 'content' => $item->content];
            }
            $messages[] = [
                'role' => 'user',
                'content' => $this->prompts->context($matches, $requestContext)."\n\nQuestion: ".$message,
            ];
            try {
                $result = $this->ai->chat($messages);
                $answer = $this->naturalize($result->content);
                $responseMeta = [
                    'provider' => $this->ai->providerName(),
                    'model' => $this->ai->model(),
                    'latency_ms' => $result->latencyMs,
                ];
            } catch (\Throwable $e) {
                report($e);
                $answer = $this->groundedFallback($school, $matches, $message);
                $responseMeta = ['provider' => 'local-grounded-fallback'];
            }
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => $responseMeta,
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return [
            'answer' => $answer,
            'conversation_id' => $conversation->id,
            'sources' => collect($matches)->map(fn ($row) => array_filter([
                'title' => $row->title,
                'type' => $row->source_type,
                'url' => $this->publicSourceUrl($row->source_url),
                'updated_at' => $row->updated_at?->toIso8601String(),
            ], fn ($value) => filled($value)))->values()->all(),
        ];
    }

    private function publicSourceUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        if (Str::contains($url, ['localhost', '127.0.0.1'])) {
            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            return $path.($query ? '?'.$query : '');
        }

        return $url;
    }

    private function groundedFallback(School $school, array $matches, string $message): string
    {
        $question = Str::lower($message);
        if (Str::contains($question, 'nama') && Str::contains($question, 'sekolah')) {
            return 'Ini '.$school->name." 😊 Kalau kamu mau, Lala juga bisa bantu cari info program, fasilitas, prestasi, atau PPDB.";
        }

        $contact = $school->contact ?? [];
        if (Str::contains($question, ['alamat', 'lokasi'])) {
            $address = trim((string) data_get($contact, 'address'));
            if ($address === '') {
                $address = trim(implode(', ', array_filter([
                    data_get($contact, 'city'),
                    data_get($contact, 'province'),
                ])));
            }

            return $address !== ''
                ? 'Alamatnya di '.$address.'. Kalau mau, Lala bisa bantu cari nomor telepon atau info PPDB juga.'
                : 'Maaf, Lala belum menemukan alamat sekolahnya.';
        }
        if (Str::contains($question, ['telepon', 'nomor'])) {
            $phone = data_get($contact, 'phone');

            return filled($phone)
                ? 'Nomor telepon sekolahnya '.$phone.'.'
                : 'Maaf, Lala belum menemukan nomor telepon sekolahnya.';
        }
        if (Str::contains($question, ['email', 'surel'])) {
            $email = data_get($contact, 'email');

            return filled($email)
                ? 'Email sekolahnya '.$email.'.'
                : 'Maaf, Lala belum menemukan email sekolahnya.';
        }

        $candidate = collect($matches)->first(function ($row) use ($question): bool {
            if (! Str::contains($question, ['prestasi', 'juara', 'penghargaan', 'kompetisi'])) {
                return false;
            }

            return Str::contains(Str::lower($row->title.' '.$row->content), [
                'prestasi', 'juara', 'penghargaan', 'kompetisi', 'robotik',
            ]);
        }) ?? ($matches[0] ?? null);

        $content = trim((string) ($candidate?->content ?? ''));
        if (($jsonStart = strpos($content, '{')) !== false) {
            $content = substr($content, 0, $jsonStart);
        }
        $content = preg_replace('/https?:\\/\\/\\S+|localhost\\S*/iu', '', $content);
        $content = trim((string) preg_replace('/\\s+/u', ' ', $content));

        return $content !== ''
            ? (filled($candidate?->title) ? $candidate->title.': ' : '').Str::limit($content, 700)
            : $this->notFoundResponse();
    }

    private function smallTalkResponse(School $school, string $message): ?string
    {
        $question = trim(Str::lower($message));

        if (preg_match('/^(halo|hai|hi|hei|assalamualaikum|pagi|siang|sore|malam)\\b/u', $question)) {
            return 'Hai! Lala di sini 😊 Ada yang ingin kamu cari tentang '.$school->name.'?';
        }

        if (Str::contains($question, ['terima kasih', 'makasih', 'thanks'])) {
            return 'Sama-sama 😊 Kalau masih butuh info sekolah, panggil Lala lagi ya.';
        }

        if (Str::contains($question, ['bisa bantu', 'tolong bantu', 'apa yang bisa'])) {
            return 'Tentu. Lala bisa bantu cari info PPDB, program, fasilitas, prestasi, alamat, dan kontak sekolah.';
        }

        return null;
    }

    private function notFoundResponse(): string
    {
        return 'Maaf, Lala belum menemukan info itu di data sekolah. Coba tanya tentang PPDB, program, fasilitas, prestasi, alamat, atau kontak sekolah ya.';
    }

    private function naturalize(string $answer): string
    {
        $answer = trim($answer);
        $answer = preg_replace(
            '/^(?:berdasarkan|dari)\\s+(?:informasi|data)(?:\\s+yang\\s+(?:tersedia|diberikan))?[,:]?\\s*/iu',
            '',
            $answer
        );
        $answer = preg_replace('/^informasi yang tersedia[:,]?\\s*/iu', '', $answer);

        return trim((string) $answer) !== '' ? trim((string) $answer) : $this->notFoundResponse();
    }

    private function conversation(School $school, ?int $id): AiConversation
    {
        if ($id) {
            $conversation = AiConversation::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereKey($id)
                ->first();
            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::withoutGlobalScopes()->create([
            'school_id' => $school->id,
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'title' => 'Percakapan baru',
            'last_message_at' => now(),
        ]);
    }
}
