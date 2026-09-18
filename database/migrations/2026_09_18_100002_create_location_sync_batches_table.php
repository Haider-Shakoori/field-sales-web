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
        Schema::create('location_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_uuid');
            $table->unsignedInteger('point_count');
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique('batch_uuid', 'lsb_batch_uuid_unique');
            $table->index(['tenant_id', 'device_id'], 'lsb_tenant_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_sync_batches');
    }
};
