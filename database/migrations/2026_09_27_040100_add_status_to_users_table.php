<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal account status + audit-friendly columns.
 *
 * Disabling is preferred over deleting: verification history, audit records
 * and registration trails must survive a staff member leaving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('disabled_reason', 255)->nullable()->after('last_login_at');
            $table->foreignId('created_by')->nullable()->after('disabled_reason')
                ->constrained('users')->nullOnDelete();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active', 'last_login_at', 'disabled_reason', 'created_by',
            ]);
        });
    }
};
