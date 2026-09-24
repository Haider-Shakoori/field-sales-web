<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_voice_notes', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignId('visit_id')->constrained('customer_visits')->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('disk',30)->default('public'); $table->string('path',500); $table->string('mime_type',120); $table->unsignedBigInteger('size_bytes'); $table->unsignedInteger('duration_seconds')->nullable(); $table->dateTime('captured_at'); $table->text('transcript')->nullable(); $table->timestamps(); $table->index(['tenant_id','visit_id','captured_at'],'visit_voice_timeline_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('visit_voice_notes'); }
};