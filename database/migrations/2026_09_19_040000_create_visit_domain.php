<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->foreignId('work_session_id')->nullable()->constrained('work_sessions')->nullOnDelete();
            $table->boolean('is_planned')->default(false);
            $table->string('status', 30)->default('active');
            $table->string('outcome', 60)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->decimal('checkin_latitude', 10, 7);
            $table->decimal('checkin_longitude', 10, 7);
            $table->decimal('checkin_accuracy', 8, 2);
            $table->decimal('checkout_latitude', 10, 7)->nullable();
            $table->decimal('checkout_longitude', 10, 7)->nullable();
            $table->decimal('checkout_accuracy', 8, 2)->nullable();
            $table->decimal('checkin_distance_meters', 10, 2)->nullable();
            $table->decimal('checkout_distance_meters', 10, 2)->nullable();
            $table->boolean('checkin_within_geofence')->nullable();
            $table->boolean('checkout_within_geofence')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'checked_in_at']);
            $table->index(['tenant_id', 'customer_id', 'checked_in_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('visit_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('customer_visits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'visit_id']);
        });

        Schema::create('visit_suspicious_flags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('customer_visits')->cascadeOnDelete();
            $table->string('reason_code', 80);
            $table->string('severity', 20)->default('medium');
            $table->json('details')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['visit_id', 'reason_code']);
            $table->index(['tenant_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_suspicious_flags');
        Schema::dropIfExists('visit_photos');
        Schema::dropIfExists('customer_visits');
    }
};
