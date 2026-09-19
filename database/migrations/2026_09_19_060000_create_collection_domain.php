<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('customer_visits')->nullOnDelete();
            $table->string('receipt_number', 80);
            $table->dateTime('collected_at');
            $table->string('currency', 3);
            $table->decimal('amount', 18, 4);
            $table->string('payment_method', 30);
            $table->string('reference_number', 160)->nullable();
            $table->string('status', 30)->default('pending');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2);
            $table->decimal('balance_before', 18, 4)->default(0);
            $table->boolean('overpayment_flag')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('status_changed_at')->nullable();
            $table->text('status_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'customer_id', 'currency', 'collected_at']);
            $table->index(['tenant_id', 'salesman_id', 'collected_at']);
            $table->index(['tenant_id', 'status', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
