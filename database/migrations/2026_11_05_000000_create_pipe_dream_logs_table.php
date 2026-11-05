<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipe_dream_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('flow')->nullable()->index();
            // A string id, so integer, UUID and ULID keys all fit without
            // depending on the application's morph key settings.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->longText('log');
            $table->boolean('has_errors')->default(false)->index();
            $table->boolean('has_fatals')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipe_dream_logs');
    }
};
