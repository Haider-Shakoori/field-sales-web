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
        Schema::create('location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_uuid');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('horizontal_accuracy', 8, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_charging')->default(false);
            $table->string('network_status', 20)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->string('provider', 50)->nullable();
            $table->dateTime('recorded_at');
            $table->dateTime('received_at');
            $table->foreignId('sync_batch_id')->nullable()->constrained('location_sync_batches')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'client_uuid'], 'lh_tenant_client_uuid_unique');
            $table->index(['tenant_id', 'user_id', 'recorded_at'], 'lh_tenant_user_recorded');
            $table->index('recorded_at', 'lh_recorded_at');
            $table->index('sync_batch_id', 'lh_sync_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_history');
    }
};
