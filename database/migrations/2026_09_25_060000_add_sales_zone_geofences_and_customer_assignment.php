<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->json('geofence_polygon')->nullable()->after('is_active');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable()->after('email');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('assigned_salesman_id')
                ->nullable()
                ->after('territory_id')
                ->constrained('salesmen')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'assigned_salesman_id', 'is_active'],
                'customers_assigned_salesman_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_assigned_salesman_idx');
            $table->dropConstrainedForeignId('assigned_salesman_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('geofence_polygon');
        });
    }
};
