<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_voice_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('customer_visits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->string('language', 16)->nullable();
            $table->string('transcription_status', 40)->default('disabled');
            $table->longText('transcript')->nullable();
            $table->json('structured_notes')->nullable();
            $table->text('transcription_error')->nullable();
            $table->dateTime('transcribed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'visit_id']);
            $table->index(['tenant_id', 'transcription_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_voice_notes');
    }
};
