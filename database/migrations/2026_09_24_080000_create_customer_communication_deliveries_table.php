<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_communication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('kind', 50)->default('custom');
            $table->string('recipient_phone', 50);
            $table->text('message');
            $table->string('provider', 80)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid'], 'customer_comm_tenant_uuid_unique');
            $table->index(
                ['tenant_id', 'customer_id', 'created_at'],
                'customer_comm_customer_created_idx',
            );
            $table->index(
                ['tenant_id', 'status', 'channel'],
                'customer_comm_status_channel_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communication_deliveries');
    }
};
