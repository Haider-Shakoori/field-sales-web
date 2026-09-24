<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('provider_http_status')
                ->nullable()
                ->after('model');
            $table->string('provider_request_id', 191)
                ->nullable()
                ->after('provider_http_status');
            $table->unsignedSmallInteger('tool_call_count')
                ->default(0)
                ->after('completion_tokens');
            $table->decimal('estimated_cost_usd', 14, 8)
                ->nullable()
                ->after('tool_call_count');

            $table->index(
                ['tenant_id', 'role', 'created_at'],
                'ai_messages_usage_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropIndex('ai_messages_usage_idx');
            $table->dropColumn([
                'provider_http_status',
                'provider_request_id',
                'tool_call_count',
                'estimated_cost_usd',
            ]);
        });
    }
};
