<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->unsignedBigInteger('salesman_id')->nullable()->change();
        });

        Schema::create('mobile_diagnostics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity', 20)->default('error');
            $table->string('area', 80);
            $table->string('code', 120)->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'area', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_diagnostics');
    }
};
