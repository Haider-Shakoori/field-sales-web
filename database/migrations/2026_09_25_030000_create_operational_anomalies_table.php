<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('rule_code', 80);
            $table->string('severity', 20)->default('medium');
            $table->string('state', 20)->default('open');
            $table->string('title', 180);
            $table->text('summary');
            $table->json('evidence')->nullable();
            $table->string('fingerprint', 64);
            $table->dateTime('occurred_at');
            $table->dateTime('first_detected_at');
            $table->dateTime('last_detected_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fingerprint'], 'operational_anomaly_fingerprint_unique');
            $table->index(['tenant_id', 'state', 'severity', 'occurred_at'], 'operational_anomaly_state_idx');
            $table->index(['tenant_id', 'salesman_id', 'occurred_at'], 'operational_anomaly_salesman_idx');
            $table->index(['tenant_id', 'entity_type', 'rule_code'], 'operational_anomaly_rule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_anomalies');
    }
};
