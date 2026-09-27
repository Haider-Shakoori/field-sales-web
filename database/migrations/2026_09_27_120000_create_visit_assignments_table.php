<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date');
            $table->time('scheduled_time')->nullable();
            $table->string('purpose', 60)->default('sales');
            $table->string('priority', 20)->default('normal');
            $table->unsignedSmallInteger('expected_duration_minutes')->default(15);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'salesman_id', 'visit_date']);
            $table->index(['tenant_id', 'customer_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_assignments');
    }
};
