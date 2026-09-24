<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name', 180);
            $table->string('contact_person', 160)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->string('source', 40)->default('field');
            $table->string('stage', 40)->default('new');
            $table->string('priority', 20)->default('normal');
            $table->decimal('estimated_value', 18, 4)->nullable();
            $table->string('currency', 3)->default('AFN');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'stage', 'priority'], 'leads_stage_priority_idx');
            $table->index(['tenant_id', 'assigned_salesman_id', 'stage'], 'leads_salesman_stage_idx');
            $table->index(['tenant_id', 'expected_close_date', 'stage'], 'leads_close_stage_idx');
            $table->index(['tenant_id', 'source', 'stage'], 'leads_source_stage_idx');
        });

        Schema::create('lead_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->default('note');
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'occurred_at'], 'lead_activity_timeline_idx');
            $table->index(['tenant_id', 'type', 'occurred_at'], 'lead_activity_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};