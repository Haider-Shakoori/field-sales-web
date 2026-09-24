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

    public function ask(
        User $user,
        string $question,
        ?string $conversationUuid = null,
    ): array {
        $conversation = $conversationUuid
            ? $this->findOwned($user, $conversationUuid)
            : $this->create($user, $question);

        $history = $this->historyForAgent($conversation);
        $snapshot = $this->insights->snapshot($user);
        $this->recordUserMessage($conversation, $user, $question);

        $provider = (string) config('ai.provider', 'generic');
        $result = null;

        if (
            config('ai.enabled', false)
            && in_array(
                $provider,
                ['groq', 'openrouter', 'openai_compatible'],
                true,
            )
        ) {
            $agentResult = $this->agent->answerDetailed(
                $user,
                $question,
                $snapshot,
                $history,
            );

            if ($agentResult['ok'] ?? false) {
                $result = [
                    'answer' => $agentResult['answer'],
                    'source' => 'configured_ai_agent',
                    'provider' => $agentResult['provider'],
                    'model' => $agentResult['model'],
                    'tool_activity' => $agentResult['tool_activity'],
                    'usage' => $agentResult['usage'],
                    'latency_ms' => $agentResult['latency_ms'],
                    'fallback_reason' => null,
                    'provider_status' => 'connected',
                ];
            } else {
                $result = [
                    'answer' => $this->localAnswer($question, $snapshot),
                    'source' => 'fieldpulse_grounded_rules',
                    'provider' => $agentResult['provider'] ?? $provider,
                    'model' => $agentResult['model'] ?? config('ai.model'),
                    'tool_activity' => $agentResult['tool_activity'] ?? [],
                    'usage' => [],
                    'latency_ms' => $agentResult['latency_ms'] ?? null,
                    'fallback_reason' => $agentResult['error_code']
                        ?? 'provider_error',
                    'provider_status' => $agentResult['error_message']
                        ?? 'The external AI provider was unavailable.',
                ];
            }
        }

        if ($result === null) {
            $legacy = $this->insights->answer($user, $question);
            $result = [
                'answer' => $legacy['answer'],
                'source' => $legacy['source'],
                'provider' => $provider !== 'generic' ? $provider : null,
                'model' => config('ai.model'),
                'tool_activity' => [],
                'usage' => [],
                'latency_ms' => null,
                'fallback_reason' => null,
                'provider_status' => null,
            ];
        }

        $assistant = $this->recordAssistantMessage($conversation, $result);

        return [
            ...$result,
            'conversation' => $conversation->fresh(),
            'message' => $assistant,
        ];
    }

    public function serializeConversation(AiConversation $conversation): array
    {
        return [
            'uuid' => $conversation->uuid,
            'title' => $conversation->title,
            'archived' => $conversation->archived_at !== null,
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'messages' => $conversation->messages->map(fn ($message): array => [
                'uuid' => $message->uuid,
                'role' => $message->role,
                'content' => $message->content,
                'source' => $message->source,
                'provider' => $message->provider,
                'model' => $message->model,
                'fallback_reason' => $message->fallback_reason,
                'provider_status' => $message->meta['provider_status'] ?? null,
                'tool_activity' => $message->tool_summary ?? [],
                'latency_ms' => $message->latency_ms,
                'created_at' => $message->created_at?->toISOString(),
            ])->all(),
        ];
    }

    private function localAnswer(string $question, array $snapshot): string
    {
        $question = Str::lower(trim($question));

        if (Str::contains($question, ['sale', 'revenue', 'order'])) {
            return __('Approved sales in the last 7 days: :sales. Pending orders: :pending.', [
                'sales' => $this->currencySummary($snapshot['approved_sales_7d']),
                'pending' => $snapshot['pending_orders'],
            ]);
        }

        if (Str::contains($question, ['collection', 'payment', 'cash'])) {
            return __('Verified collections in the last 7 days: :collections. Pending collections: :pending.', [
                'collections' => $this->currencySummary($snapshot['verified_collections_7d']),
                'pending' => $snapshot['pending_collections'],
            ]);
        }

        if (Str::contains($question, ['visit', 'customer', 'coverage'])) {
            return __('Visits today: :visits. Active customers without a visit in 30 days: :customers.', [
                'visits' => $snapshot['visits_today'],
                'customers' => $snapshot['customers_not_visited_30_days'],
            ]);
        }

        if (Str::contains($question, ['follow', 'task', 'priority'])) {
            return __('Overdue follow-ups: :overdue. Open high-priority follow-ups: :high.', [
                'overdue' => $snapshot['overdue_followups'],
                'high' => $snapshot['high_priority_followups'],
            ]);
        }

        if (Str::contains($question, ['attendance', 'start day', 'salesman', 'team'])) {
            return __(':started of :active active salesmen have started work today.', [
                'started' => $snapshot['salesmen_started_today'],
                'active' => $snapshot['active_salesmen'],
            ]);
        }

        $top = $snapshot['recommendations'][0];

        return __($top['title']).': '
            .__($top['message'], $top['message_params'] ?? [])
            .' '.__($top['action']);
    }

    private function currencySummary(array $values): string
    {
        if ($values === []) {
            return __('none recorded');
        }

        return collect($values)
            ->map(
                fn ($value, $currency) => $currency.' '
                    .number_format((float) $value, 2)
            )
            ->implode(', ');
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
