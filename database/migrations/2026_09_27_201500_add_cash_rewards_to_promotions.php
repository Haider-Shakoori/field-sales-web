<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('promotions', function(Blueprint $table){ $table->string('reward_type',20)->default('product')->after('minimum_order_amount'); $table->decimal('cash_reward_amount',18,4)->nullable()->after('reward_quantity'); $table->string('cash_reward_currency',3)->default('AFN')->after('cash_reward_amount'); $table->foreignId('reward_product_id')->nullable()->change(); }); }
 public function down(): void { Schema::table('promotions', function(Blueprint $table){ $table->foreignId('reward_product_id')->nullable(false)->change(); $table->dropColumn(['reward_type','cash_reward_amount','cash_reward_currency']); }); }
};
