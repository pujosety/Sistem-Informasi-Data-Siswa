<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_discussions')) {
            return;
        }

        Schema::create('lms_discussions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('lms_discussions')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();
            $table->index(['school_id', 'course_id', 'parent_id', 'created_at'], 'lms_discussion_thread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_discussions');
    }
};
