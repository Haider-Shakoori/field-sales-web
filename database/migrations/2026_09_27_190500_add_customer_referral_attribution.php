<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('referred_by_salesman_id')
                ->nullable()
                ->after('assigned_salesman_id')
                ->constrained('salesmen')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'referred_by_salesman_id', 'is_active'],
                'customers_referred_by_salesman_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_referred_by_salesman_idx');
            $table->dropConstrainedForeignId('referred_by_salesman_id');
        });
    }
};
