<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_version_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_file_version_id')->unique()->constrained('workspace_file_versions')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason_code', 40)->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('scanned_bytes')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
        // Historical rows intentionally have no attestation. Missing = pending,
        // denied; no unsafe blanket backfill and no immutable-version mutation.
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_version_scans');
    }
};
