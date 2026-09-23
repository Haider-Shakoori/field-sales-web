<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesman_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('sellable_qty', 18, 4)->default(0);
            $table->decimal('damaged_qty', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'salesman_id', 'product_id'],
                'ssb_salesman_product_uq',
            );
            $table->index(
                ['tenant_id', 'salesman_id'],
                'ssb_salesman_idx',
            );
        });

        Schema::create('salesman_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('bucket', 20);
            $table->string('movement_type', 40);
            $table->decimal('quantity_change', 18, 4);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                [
                    'tenant_id',
                    'movement_type',
                    'reference_type',
                    'reference_id',
                    'product_id',
                    'bucket',
                ],
                'ssm_reference_product_uq',
            );
            $table->index(
                ['tenant_id', 'salesman_id', 'occurred_at'],
                'ssm_salesman_date_idx',
            );
            $table->index(
                ['tenant_id', 'product_id', 'occurred_at'],
                'ssm_product_date_idx',
            );
        });

        Schema::create('stock_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->string('issue_number', 80);
            $table->dateTime('issued_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'issue_number'], 'si_issue_number_uq');
            $table->index(
                ['tenant_id', 'salesman_id', 'issued_at'],
                'si_salesman_date_idx',
            );
        });

        Schema::create('stock_issue_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();

            $table->unique(
                ['stock_issue_id', 'product_id'],
                'sii_issue_product_uq',
            );
        });

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('customer_visits')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 80);
            $table->dateTime('returned_at');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->text('status_note')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('status_changed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'return_number'],
                'sr_return_number_uq',
            );
            $table->index(
                ['tenant_id', 'salesman_id', 'returned_at'],
                'sr_salesman_date_idx',
            );
            $table->index(
                ['tenant_id', 'status', 'returned_at'],
                'sr_status_date_idx',
            );
        });

        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('condition', 20);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['sales_return_id', 'product_id', 'condition'],
                'sri_return_product_condition_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('stock_issue_items');
        Schema::dropIfExists('stock_issues');
        Schema::dropIfExists('salesman_stock_movements');
        Schema::dropIfExists('salesman_stock_balances');
    }
};
