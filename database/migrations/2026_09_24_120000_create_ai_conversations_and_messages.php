<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid'], 'ai_conversations_tenant_uuid_unique');
            $table->index(
                ['tenant_id', 'user_id', 'archived_at', 'last_message_at'],
                'ai_conversations_user_history_idx',
            );
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->string('source', 80)->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('model', 191)->nullable();
            $table->string('fallback_reason', 120)->nullable();
            $table->json('tool_summary')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uuid'], 'ai_messages_tenant_uuid_unique');
            $table->index(
                ['tenant_id', 'conversation_id', 'id'],
                'ai_messages_conversation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
