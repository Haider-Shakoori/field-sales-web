<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_assignments', function (Blueprint $table): void {
            $table->foreignId('sales_manager_id')
                ->nullable()
                ->after('supervisor_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'sales_manager_id', 'effective_from', 'effective_to'],
                'supervisor_assignment_manager_window_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_assignments', function (Blueprint $table): void {
            $table->dropIndex('supervisor_assignment_manager_window_idx');
            $table->dropConstrainedForeignId('sales_manager_id');
        });
    }
};
