<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_knowledge')) {
            Schema::create('ai_knowledge', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('source_type', 40);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('title', 255);
                $table->longText('content');
                $table->text('source_url')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('content_hash', 64);
                $table->timestamps();

                $table->unique(['school_id', 'source_type', 'source_id'], 'ai_knowledge_source_unique');
                $table->index(['school_id', 'is_active'], 'ai_knowledge_school_active_index');
                $table->index(['school_id', 'source_type'], 'ai_knowledge_school_type_index');
            });
        }

        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('session_id', 255)->nullable();
                $table->string('title', 255)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'session_id'], 'ai_conversations_school_session_index');
                $table->index(['school_id', 'user_id'], 'ai_conversations_school_user_index');
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
                $table->string('role', 20);
                $table->longText('content');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at'], 'ai_messages_conversation_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_knowledge');
    }
};
