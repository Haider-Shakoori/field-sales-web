<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('expense_number', 80);
            $table->dateTime('spent_at');
            $table->string('category', 40);
            $table->string('currency', 3);
            $table->decimal('amount', 18, 4);
            $table->string('merchant', 160)->nullable();
            $table->string('reference_number', 160)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'expense_number']);
            $table->index(['tenant_id', 'salesman_id', 'spent_at'], 'exp_sales_spent_idx');
            $table->index(['tenant_id', 'status', 'spent_at'], 'exp_status_spent_idx');
            $table->index(['tenant_id', 'category', 'spent_at'], 'exp_cat_spent_idx');
        });

        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 30);
            $table->string('currency', 3)->nullable();
            $table->decimal('target_value', 18, 4);
            $table->date('period_start');
            $table->date('period_end');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'salesman_id', 'target_type', 'period_start', 'period_end'],
                'sales_target_window_uniq'
            );
            $table->index(['tenant_id', 'salesman_id', 'period_start', 'period_end'], 'sales_target_period_idx');
            $table->index(['tenant_id', 'target_type', 'period_start'], 'sales_target_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
        Schema::dropIfExists('expenses');
    }
};
