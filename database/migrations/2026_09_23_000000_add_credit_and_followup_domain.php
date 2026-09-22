<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->decimal('credit_limit', 18, 4)->nullable()->after('price_list_id');
            $table->string('credit_currency', 3)->default('AFN')->after('credit_limit');
            $table->unsignedSmallInteger('credit_terms_days')->default(30)->after('credit_currency');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('due_date')->nullable()->after('ordered_at');
            $table->index(['tenant_id', 'due_date', 'status']);
        });

        Schema::create('customer_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
            $table->string('type', 30)->default('call');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->dateTime('due_at');
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'assigned_salesman_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_follow_ups');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'due_date', 'status']);
            $table->dropColumn('due_date');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['credit_limit', 'credit_currency', 'credit_terms_days']);
        });
    }
};
