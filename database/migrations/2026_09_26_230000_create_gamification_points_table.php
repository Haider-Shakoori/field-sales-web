<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->integer('points');
            $table->dateTime('earned_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_type', 'source_type', 'source_id'], 'gamification_points_source_unique');
            $table->index(['tenant_id', 'salesman_id', 'earned_at'], 'gamification_points_salesman_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_points');
    }
};
