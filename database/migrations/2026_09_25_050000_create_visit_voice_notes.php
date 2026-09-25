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
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->dateTime('recorded_at');
            $table->string('transcription_status', 30)->default('disabled');
            $table->longText('transcript')->nullable();
            $table->json('structured_notes')->nullable();
            $table->string('transcription_provider', 60)->nullable();
            $table->string('transcription_model', 160)->nullable();
            $table->text('transcription_error')->nullable();
            $table->dateTime('transcribed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'visit_id', 'recorded_at'], 'visit_voice_notes_timeline_idx');
            $table->index(['tenant_id', 'transcription_status', 'created_at'], 'visit_voice_notes_transcription_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_voice_notes');
    }
};
