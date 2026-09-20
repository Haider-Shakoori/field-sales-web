<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_history', function (Blueprint $table): void {
            $table->index(['tenant_id', 'salesman_id', 'recorded_at'], 'location_history_track_index');
        });
    }

    public function down(): void
    {
        Schema::table('location_history', function (Blueprint $table): void {
            $table->dropIndex('location_history_track_index');
        });
    }
};
