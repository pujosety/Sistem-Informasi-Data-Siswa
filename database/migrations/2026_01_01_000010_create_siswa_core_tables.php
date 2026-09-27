<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();          // 2026/2027
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                   // IPA, IPS, TKJ
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 50);                    // X IPA 1
            $table->string('level', 20);                   // X / XI / XII
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nisn', 10)->unique();
            $table->string('nik', 16)->nullable();
            $table->string('full_name', 150);
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 30)->nullable();
            $table->string('phone', 25)->nullable();
            $table->text('address')->nullable();
            $table->string('village', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();

            // Riwayat pendidikan
            $table->string('previous_school', 150)->nullable();
            $table->string('graduation_year', 4)->nullable();
            $table->string('diploma_number', 60)->nullable();
            $table->decimal('previous_score', 5, 2)->nullable();

            // Penempatan
            $table->foreignId('class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_year', 4)->nullable();

            $table->timestamps();
        });

        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('relation', ['father', 'mother', 'guardian'])->default('father');
            $table->string('full_name', 150);
            $table->string('job', 100)->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('nik', 16)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                   // Ijazah
            $table->string('slug', 100)->unique();
            $table->string('label', 100);                  // Ijazah / SKL
            $table->text('description')->nullable();
            $table->json('accepted_mimes')->nullable();
            $table->unsignedInteger('max_size_kb')->default(2048);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted', 'pending', 'revision', 'verified', 'rejected'])
                ->default('draft');
            $table->unsignedTinyInteger('completeness')->default(0);   // 0..100
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->enum('status', ['missing', 'pending', 'valid', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['registration_id', 'document_type_id'], 'documents_registration_type_unique');
        });

        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['submit', 'approve', 'reject', 'revise', 'reset']);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['registration_id', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('academic_years');
    }
};
