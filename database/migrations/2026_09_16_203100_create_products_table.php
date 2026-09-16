<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('sku', 100);
            $table->string('unit', 50)->default('piece');
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->string('category', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
            $table->index(['tenant_id', 'name'], 'products_tenant_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
