<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'credit_limit')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->decimal('credit_limit', 18, 4)->nullable()->after('price_list_id');
            });
        }

        if (! Schema::hasColumn('customers', 'credit_currency')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('credit_currency', 3)->default('AFN')->after('credit_limit');
            });
        }

        if (! Schema::hasColumn('customers', 'credit_terms_days')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unsignedSmallInteger('credit_terms_days')->default(30)->after('credit_currency');
            });
        }

        if (! Schema::hasColumn('orders', 'due_date')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->date('due_date')->nullable()->after('ordered_at');
            });
        }

        if (! Schema::hasIndex('orders', ['tenant_id', 'due_date', 'status'])) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'due_date', 'status'],
                    'orders_tenant_due_status_idx',
                );
            });
        }

        if (! Schema::hasTable('customer_follow_ups')) {
            Schema::create('customer_follow_ups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
                $table->string('type', 30)->default('call');
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('pending');
                $table->dateTime('due_at');
                $table->text('notes')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('completion_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(
                    ['tenant_id', 'status', 'due_at'],
                    'cfu_tenant_status_due_idx',
                );
                $table->index(
                    ['tenant_id', 'assigned_salesman_id', 'status', 'due_at'],
                    'cfu_salesman_status_due_idx',
                );
                $table->index(
                    ['tenant_id', 'customer_id', 'status'],
                    'cfu_customer_status_idx',
                );
            });

            return;
        }

        if (! Schema::hasIndex('customer_follow_ups', ['tenant_id', 'status', 'due_at'])) {
            Schema::table('customer_follow_ups', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'status', 'due_at'],
                    'cfu_tenant_status_due_idx',
                );
            });
        }

        if (! Schema::hasIndex(
            'customer_follow_ups',
            ['tenant_id', 'assigned_salesman_id', 'status', 'due_at'],
        )) {
            Schema::table('customer_follow_ups', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'assigned_salesman_id', 'status', 'due_at'],
                    'cfu_salesman_status_due_idx',
                );
            });
        }

        if (! Schema::hasIndex('customer_follow_ups', ['tenant_id', 'customer_id', 'status'])) {
            Schema::table('customer_follow_ups', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'customer_id', 'status'],
                    'cfu_customer_status_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_follow_ups');

        if (Schema::hasColumn('orders', 'due_date')) {
            $this->dropIndexByColumns('orders', ['tenant_id', 'due_date', 'status']);

            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('due_date');
            });
        }

        $customerColumns = array_values(array_filter(
            ['credit_limit', 'credit_currency', 'credit_terms_days'],
            fn (string $column): bool => Schema::hasColumn('customers', $column),
        ));

        if ($customerColumns !== []) {
            Schema::table('customers', function (Blueprint $table) use ($customerColumns): void {
                $table->dropColumn($customerColumns);
            });
        }
    }

    private function dropIndexByColumns(string $tableName, array $columns): void
    {
        $index = collect(Schema::getIndexes($tableName))->first(
            fn (array $index): bool => array_values($index['columns'] ?? []) === $columns,
        );

        if (! $index || empty($index['name'])) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index['name']);
        });
    }
};
