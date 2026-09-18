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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->decimal('start_latitude', 10, 7);
            $table->decimal('start_longitude', 10, 7);
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->string('status', 50)->default('active');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_late_start')->default(false);
            $table->boolean('is_early_finish')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('corrected_at')->nullable();
            $table->string('correction_reason', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'date'], 'ws_tenant_user_date_unique');
            $table->index('status', 'ws_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
