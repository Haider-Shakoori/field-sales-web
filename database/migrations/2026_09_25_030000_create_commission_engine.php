<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('metric', 30);
            $table->decimal('rate_percent', 7, 4);
            $table->decimal('minimum_source_amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('AFN');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'metric', 'is_active'], 'commission_plan_metric_idx');
        });

        Schema::create('commission_plan_salesmen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['commission_plan_id', 'salesman_id'], 'commission_plan_salesman_unique');
            $table->index(['tenant_id', 'salesman_id']);
        });

        Schema::create('commission_earnings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->uuid('source_uuid')->nullable();
            $table->decimal('source_amount', 18, 4);
            $table->decimal('rate_percent', 7, 4);
            $table->decimal('commission_amount', 18, 4);
            $table->string('currency', 3);
            $table->dateTime('earned_at');
            $table->string('status', 20)->default('earned');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['commission_plan_id', 'salesman_id', 'source_type', 'source_id'], 'commission_source_unique');
            $table->index(['tenant_id', 'salesman_id', 'earned_at'], 'commission_salesman_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_earnings');
        Schema::dropIfExists('commission_plan_salesmen');
        Schema::dropIfExists('commission_plans');
    }
};