<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->string('basis_type', 40);
            $table->string('reward_type', 20);
            $table->decimal('rate', 18, 4);
            $table->string('currency', 3)->nullable();
            $table->decimal('minimum_basis', 18, 4)->nullable();
            $table->foreignId('salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 40)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'effective_from', 'effective_to'], 'commission_rule_effective_idx');
            $table->index(['tenant_id', 'basis_type', 'salesman_id'], 'commission_rule_basis_salesman_idx');
        });

        Schema::create('commission_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('state', 20)->default('draft');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('generated_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period_start', 'period_end'], 'commission_run_period_unique');
            $table->index(['tenant_id', 'state', 'period_start'], 'commission_run_state_idx');
        });

        Schema::create('commission_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_run_id')->constrained('commission_runs')->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->constrained('commission_rules')->restrictOnDelete();
            $table->foreignId('salesman_id')->constrained('salesmen')->restrictOnDelete();
            $table->string('basis_type', 40);
            $table->string('reward_type', 20);
            $table->string('currency', 3);
            $table->decimal('basis_value', 18, 4);
            $table->decimal('rate', 18, 4);
            $table->decimal('commission_amount', 18, 4);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(
                ['commission_run_id', 'commission_rule_id', 'salesman_id', 'currency'],
                'commission_run_rule_salesman_currency_unique'
            );
            $table->index(['tenant_id', 'salesman_id', 'currency'], 'commission_line_salesman_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_run_lines');
        Schema::dropIfExists('commission_runs');
        Schema::dropIfExists('commission_rules');
    }
};
