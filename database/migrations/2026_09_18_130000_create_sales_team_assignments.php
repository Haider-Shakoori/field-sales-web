<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code', 60);
            $table->string('first_name', 120);
            $table->string('last_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('platform', 30)->default('android')->after('manufacturer');
            $table->string('os_version', 50)->nullable()->after('platform');
            $table->foreignId('revoked_by')
                ->nullable()
                ->after('revoked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('revocation_reason', 255)->nullable()->after('revoked_by');
        });

        Schema::create('salesman_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Batch 4 creates territories/routes and adds the foreign keys.
            // The nullable identifiers are present now so assignment history
            // can be extended without rebuilding the table.
            $table->unsignedBigInteger('territory_id')->nullable();
            $table->unsignedBigInteger('route_id')->nullable();

            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('supervisors')
                ->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'salesman_id', 'effective_from'],
                'salesman_assignment_start_unique'
            );
            $table->index(
                ['tenant_id', 'salesman_id', 'effective_from', 'effective_to'],
                'salesman_assignment_window_idx'
            );
        });

        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Territory becomes a real foreign key in Batch 4.
            $table->unsignedBigInteger('territory_id')->nullable();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'supervisor_id', 'branch_id', 'effective_from'],
                'supervisor_assignment_start_unique'
            );
            $table->index(
                ['tenant_id', 'supervisor_id', 'effective_from', 'effective_to'],
                'supervisor_assignment_window_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_assignments');
        Schema::dropIfExists('salesman_assignments');

        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn([
                'platform',
                'os_version',
                'revocation_reason',
            ]);
        });

        Schema::dropIfExists('supervisors');
    }
};
