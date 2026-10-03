<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('approval_status', 20)->default('approved')->after('is_active');
            $table->dateTime('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(true)->after('approved_by');
            $table->string('management_status', 20)->default('active')->after('is_primary');
            $table->dateTime('management_status_at')->nullable()->after('management_status');
            $table->foreignId('management_status_by')->nullable()->after('management_status_at')->constrained('users')->nullOnDelete();
            $table->text('management_status_reason')->nullable()->after('management_status_by');

            $table->index(['tenant_id', 'approval_status']);
            $table->index(['tenant_id', 'management_status']);
        });

        Schema::create('device_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->string('severity', 20)->default('info');
            $table->json('context')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'device_id', 'occurred_at'], 'device_activity_device_time_idx');
            $table->index(['tenant_id', 'event', 'occurred_at'], 'device_activity_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activity_logs');

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'approval_status']);
            $table->dropIndex(['tenant_id', 'management_status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('management_status_by');
            $table->dropColumn([
                'approval_status',
                'approved_at',
                'is_primary',
                'management_status',
                'management_status_at',
                'management_status_reason',
            ]);
        });
    }
};
