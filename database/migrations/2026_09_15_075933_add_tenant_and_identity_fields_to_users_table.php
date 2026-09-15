<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->after('id');
            $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
            $table->string('phone', 50)->nullable()->after('password');
            $table->string('role', 50)->default('member')->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('role');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['uuid']);
            $table->dropForeign(['tenant_id']);
            $table->dropSoftDeletes();
            $table->dropColumn(['uuid', 'tenant_id', 'phone', 'role', 'is_active', 'last_login_at']);
        });
    }
};
