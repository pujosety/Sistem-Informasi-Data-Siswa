<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_assignment_submissions', function (Blueprint $table): void {
            $table->foreignId('workspace_file_version_id')->nullable()->after('file_path')
                ->constrained('workspace_file_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lms_assignment_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_file_version_id');
        });
    }
};
