<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiConversationService
{
    public function __construct(
        private readonly AiInsightsService $insights,
        private readonly AiInsightsAgentService $agent,
    ) {}

    public function recent(User $user, int $limit = 40): Collection
    {
        return AiConversation::query()
            ->forUser($user)
            ->active()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function archived(User $user, int $limit = 20): Collection
    {
        return AiConversation::query()
            ->forUser($user)
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->limit($limit)
            ->get();
    }

    public function findOwned(User $user, string $uuid): AiConversation
    {
        return AiConversation::query()
            ->forUser($user)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(User $user, string $firstQuestion): AiConversation
    {
        $title = trim(Str::squish($firstQuestion));

        if ($title === '') {
            $title = __('New conversation');
        }

        $retentionDays = max(0, (int) config('ai.history_retention_days', 90));

        return AiConversation::create([
            'user_id' => $user->id,
            'title' => Str::limit($title, 90, '…'),
            'last_message_at' => now(),
            'expires_at' => $retentionDays > 0
                ? now()->addDays($retentionDays)
                : null,
        ]);
    }

    public function historyForAgent(AiConversation $conversation): array
    {
        $limit = min(40, max(2, (int) config('ai.history_messages', 20)));

        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    public function recordUserMessage(
        AiConversation $conversation,
        User $user,
        string $content,
    ): AiMessage {
        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => trim($content),
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at ?? now(),
            'archived_at' => null,
        ]);

        return $message;
    }

    public function recordAssistantMessage(
        AiConversation $conversation,
        array $result,
    ): AiMessage {
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => (string) $result['answer'],
            'source' => $result['source'] ?? null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'fallback_reason' => $result['fallback_reason'] ?? null,
            'tool_summary' => $result['tool_activity'] ?? null,
            'latency_ms' => $result['latency_ms'] ?? null,
            'input_tokens' => $result['usage']['prompt_tokens'] ?? null,
            'output_tokens' => $result['usage']['completion_tokens'] ?? null,
            'meta' => [
                'provider_status' => $result['provider_status'] ?? null,
            ],
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at ?? now(),
        ]);

        return $message;
    }

    public function archive(AiConversation $conversation): void
    {
        $conversation->update(['archived_at' => now()]);
    }

    public function restore(AiConversation $conversation): void
    {
        $conversation->update(['archived_at' => null]);
    }
}
