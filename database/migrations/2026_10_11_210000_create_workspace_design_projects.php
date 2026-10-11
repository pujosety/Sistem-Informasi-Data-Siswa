<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_design_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 180);
            $table->json('document');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'owner_id', 'updated_at'], 'workspace_design_owner_index');
        });
        Schema::create('workspace_design_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_design_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_file_version_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['workspace_design_project_id', 'workspace_file_version_id'], 'workspace_design_asset_version_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('workspace_design_assets');
        Schema::dropIfExists('workspace_design_projects');
    }
};
