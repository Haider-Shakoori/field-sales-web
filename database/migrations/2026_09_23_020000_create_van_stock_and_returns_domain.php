<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesmen', function (Blueprint $table): void {
            $table->boolean('van_stock_enabled')->default(false)->after('is_active');
        });

        Schema::create('salesman_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('sellable_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('damaged_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'salesman_id', 'product_id'], 'vsb_salesman_product_uq');
            $table->index(['tenant_id', 'salesman_id'], 'vsb_salesman_idx');
        });

        Schema::create('customer_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('customer_visits')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('return_number', 80);
            $table->dateTime('returned_at');
            $table->string('status', 20)->default('pending');
            $table->string('reason', 120);
            $table->text('notes')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('status_changed_at')->nullable();
            $table->text('status_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'return_number'], 'cr_tenant_number_uq');
            $table->index(['tenant_id', 'salesman_id', 'returned_at'], 'cr_salesman_date_idx');
            $table->index(['tenant_id', 'status', 'returned_at'], 'cr_status_date_idx');
        });

        Schema::create('customer_return_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_sku', 100);
            $table->string('product_name', 180);
            $table->string('unit', 30);
            $table->decimal('quantity', 18, 4);
            $table->string('condition', 20);
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_return_id'], 'cri_return_idx');
            $table->index(['tenant_id', 'product_id'], 'cri_product_idx');
        });

        Schema::create('salesman_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_return_id')->nullable()->constrained('customer_returns')->nullOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('sellable_delta', 18, 4)->default(0);
            $table->decimal('reserved_delta', 18, 4)->default(0);
            $table->decimal('damaged_delta', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'salesman_id', 'occurred_at'], 'ssm_salesman_date_idx');
            $table->index(['tenant_id', 'product_id', 'occurred_at'], 'ssm_product_date_idx');
            $table->unique(['order_id', 'product_id', 'movement_type'], 'ssm_order_product_type_uq');
            $table->unique(['customer_return_id', 'product_id', 'movement_type'], 'ssm_return_product_type_uq');
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'Inventory View', 'slug' => 'inventory:view', 'group' => 'inventory', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Inventory Manage', 'slug' => 'inventory:manage', 'group' => 'inventory', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['inventory:view', 'inventory:manage'])
            ->pluck('id', 'slug');

        $grants = [
            'owner' => ['inventory:view', 'inventory:manage'],
            'company_admin' => ['inventory:view', 'inventory:manage'],
            'sales_manager' => ['inventory:view', 'inventory:manage'],
            'supervisor' => ['inventory:view'],
            'salesman' => ['inventory:view'],
            'warehouse_user' => ['inventory:view', 'inventory:manage'],
            'auditor' => ['inventory:view'],
        ];

        foreach ($grants as $roleSlug => $permissionSlugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionSlugs as $permissionSlug) {
                    $permissionId = $permissionIds[$permissionSlug] ?? null;
                    if ($permissionId) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['inventory:view', 'inventory:manage'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('salesman_stock_movements');
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('salesman_stock_balances');

        Schema::table('salesmen', function (Blueprint $table): void {
            $table->dropColumn('van_stock_enabled');
        });
    }
};
