<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_file_shares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('workspace_file_id')->constrained('workspace_files')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'recipient_id', 'revoked_at'], 'workspace_shares_received_index');
            $table->index(['workspace_file_id', 'recipient_id'], 'workspace_shares_file_recipient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_file_shares');
    }
};
