<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->unsignedTinyInteger('battery_level')->nullable()->after('last_seen_at');
            $table->boolean('is_charging')->nullable()->after('battery_level');
            $table->boolean('power_save_mode')->nullable()->after('is_charging');
            $table->boolean('battery_optimization_exempt')->nullable()->after('power_save_mode');
            $table->boolean('background_restricted')->nullable()->after('battery_optimization_exempt');

            $table->boolean('location_services_enabled')->nullable()->after('background_restricted');
            $table->string('location_permission', 30)->nullable()->after('location_services_enabled');
            $table->string('background_location_permission', 30)->nullable()->after('location_permission');
            $table->string('notification_permission', 30)->nullable()->after('background_location_permission');
            $table->boolean('background_tracking_active')->nullable()->after('notification_permission');
            $table->boolean('workday_active')->default(false)->after('background_tracking_active');

            $table->string('network_type', 30)->nullable()->after('workday_active');
            $table->unsignedBigInteger('storage_free_mb')->nullable()->after('network_type');
            $table->unsignedBigInteger('storage_total_mb')->nullable()->after('storage_free_mb');

            $table->unsignedInteger('pending_sync_count')->default(0)->after('storage_total_mb');
            $table->unsignedInteger('failed_sync_count')->default(0)->after('pending_sync_count');
            $table->unsignedInteger('blocked_sync_count')->default(0)->after('failed_sync_count');
            $table->dateTime('last_sync_at')->nullable()->after('blocked_sync_count');

            $table->boolean('is_physical_device')->nullable()->after('last_sync_at');
            $table->boolean('root_signal_detected')->nullable()->after('is_physical_device');
            $table->boolean('mock_location_detected')->default(false)->after('root_signal_detected');
            $table->dateTime('last_gps_fix_at')->nullable()->after('mock_location_detected');

            $table->string('health_status', 20)->default('unknown')->after('last_gps_fix_at');
            $table->json('health_issues')->nullable()->after('health_status');
            $table->dateTime('health_reported_at')->nullable()->after('health_issues');

            $table->index(['tenant_id', 'health_status']);
            $table->index(['tenant_id', 'health_reported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'health_status']);
            $table->dropIndex(['tenant_id', 'health_reported_at']);

            $table->dropColumn([
                'battery_level',
                'is_charging',
                'power_save_mode',
                'battery_optimization_exempt',
                'background_restricted',
                'location_services_enabled',
                'location_permission',
                'background_location_permission',
                'notification_permission',
                'background_tracking_active',
                'workday_active',
                'network_type',
                'storage_free_mb',
                'storage_total_mb',
                'pending_sync_count',
                'failed_sync_count',
                'blocked_sync_count',
                'last_sync_at',
                'is_physical_device',
                'root_signal_detected',
                'mock_location_detected',
                'last_gps_fix_at',
                'health_status',
                'health_issues',
                'health_reported_at',
            ]);
        });
    }
};
