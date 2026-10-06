<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 30)->nullable();
            $table->string('topic', 80)->default('Umum');
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'contact_messages_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
