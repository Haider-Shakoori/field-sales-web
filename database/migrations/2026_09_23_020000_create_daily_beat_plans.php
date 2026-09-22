<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_beat_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('salesman_assignments')
                ->nullOnDelete();
            $table->foreignId('route_id')
                ->nullable()
                ->constrained('routes')
                ->nullOnDelete();
            $table->date('plan_date');
            $table->string('status', 20)->default('published');
            $table->string('source_type', 20);
            $table->string('algorithm_version', 50);
            $table->unsignedSmallInteger('total_stops')->default(0);
            $table->unsignedInteger('planned_visit_minutes')->default(0);
            $table->unsignedInteger('estimated_distance_m')->default(0);
            $table->unsignedSmallInteger('missing_coordinates')->default(0);
            $table->json('warnings')->nullable();
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'salesman_id', 'plan_date'],
                'beat_plan_salesman_date_uq',
            );
            $table->index(
                ['tenant_id', 'plan_date', 'status'],
                'beat_plan_date_status_idx',
            );
            $table->index(
                ['tenant_id', 'route_id', 'plan_date'],
                'beat_plan_route_date_idx',
            );
        });

        Schema::create('daily_beat_plan_stops', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beat_plan_id')
                ->constrained('daily_beat_plans')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('source_route_sequence')->nullable();
            $table->unsignedSmallInteger('sequence_number');
            $table->string('priority_tier', 20)->default('routine');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->json('reason_codes')->nullable();
            $table->json('signals')->nullable();
            $table->unsignedInteger('estimated_distance_from_previous_m')->nullable();
            $table->unsignedSmallInteger('planned_visit_minutes')->default(10);
            $table->foreignId('completed_visit_id')
                ->nullable()
                ->constrained('customer_visits')
                ->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['beat_plan_id', 'customer_id'],
                'beat_stop_plan_customer_uq',
            );
            $table->unique(
                ['beat_plan_id', 'sequence_number'],
                'beat_stop_plan_seq_uq',
            );
            $table->index(
                ['tenant_id', 'customer_id'],
                'beat_stop_tenant_customer_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_beat_plan_stops');
        Schema::dropIfExists('daily_beat_plans');
    }
};
