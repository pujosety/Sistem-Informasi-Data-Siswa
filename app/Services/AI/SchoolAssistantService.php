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

    public function answer(School $school, string $message, ?int $conversationId = null): array
    {
        $conversation = $this->conversation($school, $conversationId);
        $matches = $this->knowledge->search($school, $message);

        $userMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        if ($matches === []) {
            $answer = 'Informasi tersebut belum tersedia di sistem sekolah.';
            $responseMeta = ['provider' => 'local-fallback'];
        } else {
            $history = $conversation->messages()
                ->latest('created_at')
                ->limit(9)
                ->get()
                ->reverse()
                ->reject(fn (AiMessage $item) => $item->id === $userMessage->id);
            $messages = [['role' => 'system', 'content' => $this->prompts->system($school, $matches)]];
            foreach ($history as $item) {
                $messages[] = ['role' => $item->role, 'content' => $item->content];
            }
            $messages[] = [
                'role' => 'user',
                'content' => $this->prompts->context($matches)."\n\nQuestion: ".$message,
            ];
            $result = $this->ai->chat($messages);
            $answer = $result->content;
            $responseMeta = ['provider' => $this->ai->providerName(), 'model' => $this->ai->model(), 'latency_ms' => $result->latencyMs];
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
                'url' => $row->source_url,
                'updated_at' => $row->updated_at?->toIso8601String(),
            ], fn ($value) => filled($value)))->values()->all(),
        ];
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
