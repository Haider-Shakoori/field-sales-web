<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('gamification_bonuses', function(Blueprint $table){
   $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
   $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
   $table->foreignId('sales_target_id')->constrained('sales_targets')->cascadeOnDelete();
   $table->unsignedSmallInteger('milestone_percent'); $table->decimal('amount',18,4)->default(0);
   $table->string('currency',3)->default('AFN'); $table->string('status',20)->default('earned');
   $table->dateTime('earned_at'); $table->dateTime('approved_at')->nullable();
   $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
   $table->unique(['tenant_id','sales_target_id','milestone_percent'],'gamification_bonus_milestone_unique');
  });
 }
 public function down(): void { Schema::dropIfExists('gamification_bonuses'); }
};
