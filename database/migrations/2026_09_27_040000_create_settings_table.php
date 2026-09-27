<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings store for the Admin Control Center.
 *
 * Key/value with a declared type and group so the settings UI can be driven
 * from the database rather than hard-coded config files. Secrets (APP_KEY,
 * DB credentials) are deliberately NOT stored here — those stay in .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            // string|bool|int|json|text|image — drives casting and the form control
            $table->string('type', 20)->default('string');
            $table->string('group', 50)->default('general');
            $table->string('label', 150)->nullable();
            $table->string('hint', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
