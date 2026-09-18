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
        Schema::create('current_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
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
            $table->timestamp('updated_at')->nullable();

            $table->unique(['tenant_id', 'user_id'], 'cl_tenant_user_unique');
            $table->index('recorded_at', 'cl_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_locations');
    }
};
