<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('type', 30)->default('meeting');
            $table->string('status', 30)->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('reminder_minutes_before')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid'], 'appointments_tenant_uuid_unique');
            $table->index(['tenant_id', 'starts_at', 'status'], 'appointments_calendar_idx');
            $table->index(
                ['tenant_id', 'assigned_salesman_id', 'starts_at'],
                'appointments_salesman_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
