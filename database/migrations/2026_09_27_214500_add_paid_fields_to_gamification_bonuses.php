<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('gamification_bonuses',function(Blueprint $t){$t->dateTime('paid_at')->nullable()->after('approved_by');$t->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();});}
 public function down(): void {Schema::table('gamification_bonuses',function(Blueprint $t){$t->dropConstrainedForeignId('paid_by');$t->dropColumn('paid_at');});}
};
