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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_uuid', 255);
            $table->string('installation_uuid', 255);
            $table->string('device_model', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('android_version', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('push_token', 500)->nullable();
            $table->string('fcm_token', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('device_uuid');
            $table->unique('installation_uuid');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
