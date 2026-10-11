<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serializes admission across owners, even before the first session.
        Schema::create('workspace_upload_capacity', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('workspace_upload_capacity')->insert(['id' => 1]);

        Schema::create('workspace_upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            // Public folder identity is re-authorized at finalization, not a raw ID.
            $table->uuid('parent_public_id')->nullable();
            $table->string('name', 255);
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->unsignedInteger('next_index')->default(0);
            $table->char('checksum_sha256', 64);
            $table->json('receipts');
            $table->uuid('temp_key')->unique();
            $table->uuid('target_public_id')->unique();
            $table->uuid('result_public_id')->nullable();
            $table->string('state', 16)->default('open');
            $table->unsignedBigInteger('reserved_bytes');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['owner_id', 'school_id', 'state'], 'workspace_upload_owner_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_upload_sessions');
        Schema::dropIfExists('workspace_upload_capacity');
    }
};
