<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->string('contact_person', 160)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('alternate_phone', 60)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geofence_radius_meters')->default(100);
            $table->uuid('offline_uuid')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'offline_uuid']);
            $table->index(['tenant_id', 'territory_id', 'is_active']);
            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->json('weekdays')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'territory_id', 'is_active']);
            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });

        Schema::create('route_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->unsignedSmallInteger('planned_visit_minutes')->default(10);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'customer_id']);
            $table->unique(['route_id', 'sequence_number']);
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::table('salesman_assignments', function (Blueprint $table) {
            $table->foreign('territory_id', 'salesman_assignment_territory_fk')
                ->references('id')
                ->on('territories')
                ->nullOnDelete();
            $table->foreign('route_id', 'salesman_assignment_route_fk')
                ->references('id')
                ->on('routes')
                ->nullOnDelete();
        });

        Schema::table('supervisor_assignments', function (Blueprint $table) {
            $table->foreign('territory_id', 'supervisor_assignment_territory_fk')
                ->references('id')
                ->on('territories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_assignments', function (Blueprint $table) {
            $table->dropForeign('supervisor_assignment_territory_fk');
        });

        Schema::table('salesman_assignments', function (Blueprint $table) {
            $table->dropForeign('salesman_assignment_route_fk');
            $table->dropForeign('salesman_assignment_territory_fk');
        });

        Schema::dropIfExists('route_customers');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('territories');
    }
};
