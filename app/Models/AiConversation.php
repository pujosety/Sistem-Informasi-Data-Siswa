<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use BelongsToSchool;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'school_id', 'user_id', 'session_id', 'title', 'last_message_at',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
