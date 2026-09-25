<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->index(['tenant_id', 'latitude', 'longitude'], 'users_tenant_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_tenant_location_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
