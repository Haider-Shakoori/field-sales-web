<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table): void {
            $table->string('vehicle_reference', 120)->nullable()->after('corrections');
            $table->decimal('odometer_start_km', 12, 2)->nullable()->after('vehicle_reference');
            $table->decimal('odometer_end_km', 12, 2)->nullable()->after('odometer_start_km');
            $table->decimal('gps_distance_km', 12, 3)->nullable()->after('odometer_end_km');
            $table->dateTime('distance_calculated_at')->nullable()->after('gps_distance_km');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->decimal('fuel_liters', 12, 3)->nullable()->after('amount');
            $table->decimal('fuel_unit_price', 18, 4)->nullable()->after('fuel_liters');
            $table->decimal('odometer_km', 12, 2)->nullable()->after('fuel_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn([
                'fuel_liters',
                'fuel_unit_price',
                'odometer_km',
            ]);
        });

        Schema::table('work_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'vehicle_reference',
                'odometer_start_km',
                'odometer_end_km',
                'gps_distance_km',
                'distance_calculated_at',
            ]);
        });
    }
};
