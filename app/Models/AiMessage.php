<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $table = 'ai_messages';

    protected $fillable = ['conversation_id', 'role', 'content', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
