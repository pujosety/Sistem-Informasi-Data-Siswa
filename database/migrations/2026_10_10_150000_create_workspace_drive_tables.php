<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_folders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_folder_id')->nullable()
                ->constrained('workspace_folders')->nullOnDelete();
            $table->string('name', 180);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'owner_id', 'parent_folder_id'], 'workspace_folders_owner_tree_index');
        });

        Schema::create('workspace_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_folder_id')->nullable()
                ->constrained('workspace_folders')->nullOnDelete();
            $table->string('name', 255);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('source_application', 40)->default('drive');
            $table->unsignedInteger('current_version')->default(1);
            $table->boolean('is_favorite')->default(false);
            $table->json('permission_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'owner_id', 'parent_folder_id'], 'workspace_files_owner_folder_index');
            $table->index(['school_id', 'owner_id', 'updated_at'], 'workspace_files_owner_recent_index');
        });

        Schema::create('workspace_file_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_file_id')->constrained('workspace_files')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 40);
            $table->string('path', 1024);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_file_id', 'version_number'], 'workspace_file_versions_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_file_versions');
        Schema::dropIfExists('workspace_files');
        Schema::dropIfExists('workspace_folders');
    }
};
