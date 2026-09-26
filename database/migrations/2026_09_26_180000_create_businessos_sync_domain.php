<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_os_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger', 30)->default('scheduled');
            $table->string('status', 30)->default('queued');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->json('requested_streams')->nullable();
            $table->json('counts')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('business_os_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('stream', 60);
            $table->text('cursor')->nullable();
            $table->string('status', 30)->default('never');
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'stream']);
        });

        Schema::create('business_os_entity_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 60);
            $table->uuid('local_uuid');
            $table->string('external_id', 191);
            $table->string('external_version', 191)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'entity_type', 'local_uuid'],
                'business_os_link_local_unique',
            );
            $table->unique(
                ['tenant_id', 'entity_type', 'external_id'],
                'business_os_link_external_unique',
            );
        });

        Schema::create('business_os_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 191);
            $table->string('event_type', 80);
            $table->string('entity_type', 60);
            $table->uuid('local_uuid');
            $table->json('payload');
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_key']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'entity_type', 'local_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_os_outbox_events');
        Schema::dropIfExists('business_os_entity_links');
        Schema::dropIfExists('business_os_sync_states');
        Schema::dropIfExists('business_os_sync_runs');
    }
};
