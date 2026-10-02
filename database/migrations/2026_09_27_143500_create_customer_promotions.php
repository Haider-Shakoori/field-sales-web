<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->foreignId('qualifying_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->decimal('qualifying_quantity', 18, 4)->nullable();
            $table->decimal('minimum_order_amount', 18, 4)->nullable();
            $table->foreignId('reward_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('reward_quantity', 18, 4);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('line_total');
            $table->foreignId('promotion_id')->nullable()->after('is_bonus')->constrained('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('is_bonus');
        });

        Schema::dropIfExists('promotions');
    }
};
