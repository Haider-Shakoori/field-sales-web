<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('category', 60);
            $table->string('priority', 20)->default('normal');
            $table->string('title', 180);
            $table->text('message');
            $table->json('data')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'read_at'], 'op_notif_user_read_idx');
            $table->index(['tenant_id', 'category', 'created_at'], 'op_notif_category_idx');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('database_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('order_updates')->default(true);
            $table->boolean('collection_updates')->default(true);
            $table->boolean('expense_updates')->default(true);
            $table->boolean('suspicious_alerts')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')
                ->constrained('operational_notifications')
                ->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->default('push');
            $table->string('provider', 40)->default('generic_http');
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'notif_delivery_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('operational_notifications');
    }
};
