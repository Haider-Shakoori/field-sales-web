<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('vehicle_reference', 120)->nullable()->after('odometer_km');
            $table->boolean('full_tank')->default(false)->after('vehicle_reference');
            $table->string('receipt_path')->nullable()->after('full_tank');
            $table->string('receipt_mime_type', 120)->nullable()->after('receipt_path');
            $table->unsignedBigInteger('receipt_size_bytes')->nullable()->after('receipt_mime_type');
            $table->dateTime('receipt_uploaded_at')->nullable()->after('receipt_size_bytes');
            $table->index(
                ['tenant_id', 'category', 'vehicle_reference', 'spent_at'],
                'fuel_vehicle_spent_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('fuel_vehicle_spent_idx');
            $table->dropColumn([
                'vehicle_reference',
                'full_tank',
                'receipt_path',
                'receipt_mime_type',
                'receipt_size_bytes',
                'receipt_uploaded_at',
            ]);
        });
    }
};
