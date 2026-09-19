<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->decimal('base_price', 18, 4)->default(0);
            $table->string('currency', 3)->default('AFN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->string('currency', 3)->default('AFN');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(
                ['tenant_id', 'is_active', 'effective_from', 'effective_to'],
                'price_lists_active_window_idx'
            );
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('min_quantity', 18, 4)->default(1);
            $table->decimal('price', 18, 4);
            $table->timestamps();

            $table->unique(
                ['price_list_id', 'product_id', 'min_quantity'],
                'price_list_item_tier_unique'
            );
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                ->nullable()
                ->after('territory_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['tenant_id', 'price_list_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'price_list_id']);
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('products');
    }
};
