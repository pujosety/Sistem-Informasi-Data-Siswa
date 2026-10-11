<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_wopi_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_file_id')->constrained('workspace_files')->restrictOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->boolean('can_write')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_file_id', 'user_id', 'expires_at'], 'workspace_wopi_tokens_file_user_exp_index');
        });

        Schema::create('workspace_wopi_locks', function (Blueprint $table): void {
            $table->foreignId('workspace_file_id')->primary()
                ->constrained('workspace_files')->restrictOnDelete();
            $table->string('lock_value', 1024)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_wopi_locks');
        Schema::dropIfExists('workspace_wopi_tokens');
    }
};
