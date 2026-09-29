<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employment, as distinct from identity.
 *
 * WHY THIS TABLE EXISTS WHEN USERS ALREADY EXISTS
 *
 * Staff today are `users` with a role, and that is adequate for an SIS. It is
 * not adequate for HRIS, which needs employment status, position, contract
 * dates and a department assignment — none of which is a property of an
 * account. A teacher who retires keeps their login; a student account and a
 * contract are different things with different lifetimes.
 *
 * WHY `user_id` IS NULLABLE
 *
 * Two reasons, both real. A person can be an employee before they are given a
 * login, which is how a school actually hires. And `parent` already exists as
 * a table that is deliberately NOT a user — a relative who is also staff must
 * not conflate the two, and the same reasoning applies here.
 *
 * WHY NOTHING IS DUPLICATED FROM USERS
 *
 * `name`, `email`, `phone` and `is_active` already live on `users` and stay
 * there. Copying them would create two sources that disagree the first time a
 * teacher changes their phone number. An employee is a person; this row is
 * their employment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            // Human-facing staff number. Unique when set, but several employees
            // may have none yet, so a plain unique index would not do — and the
            // nullable-unique behaviour is exactly what Laravel emits for
            // unique() on a nullable column in MySQL.
            $table->string('employee_number', 30)->nullable()->unique();

            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('position', 100)->nullable();

            // active | inactive | on_leave | resigned
            $table->string('employment_status', 20)->default('active');

            // Which of the two a subject/homeroom assignment is expressed
            // against. A teacher is employed and is rostered; these are not the
            // same question.
            $table->string('employment_type', 30)->default('permanent');

            $table->date('hire_date')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();

            /*
             * Employment address, which is not the account's. A teacher's work
             * address and their personal contact details are different records
             * with different visibility.
             */
            $table->string('work_address', 255)->nullable();
            $table->string('photo_path', 255)->nullable();

            /*
             * Former staff are kept rather than deleted. A resignation must not
             * erase the grade history they wrote, and a hard delete would
             * cascade it away.
             */
            $table->timestamp('resigned_at')->nullable();
            $table->string('resignation_reason', 255)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'employment_status'], 'employees_dept_status_index');
            $table->index('employment_status', 'employees_status_index');
        });

        /*
         * Backfill is deliberately NOT done.
         *
         * Existing staff are `users` carrying a staff role, and the mapping from
         * a role to an employment record is a judgement — a `kesiswaan` might be
         * a teacher, an office clerk, or a vice principal, and only the school
         * knows which. Guessing would write false HR records that later get
         * exported to a payslip.
         *
         * Until an operator creates them, `employees` is empty and
         * EmployeeService::forUser() returns null — which every caller must
         * handle, because "staff member with no HR record" is a real state
         * during onboarding and is not an error.
         */    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
