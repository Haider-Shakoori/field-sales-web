<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Str;

class AiChatService
{
    public function __construct(
        private readonly AiConversationService $conversations,
        private readonly AiInsightsService $insights,
        private readonly AiInsightsAgentService $agent,
    ) {}

    public function ask(
        User $user,
        string $question,
        ?string $conversationUuid = null,
    ): array {
        $conversation = $conversationUuid
            ? $this->conversations->findOwned($user, $conversationUuid)
            : $this->conversations->create($user, $question);

        $history = $this->conversations->historyForAgent($conversation);
        $snapshot = $this->insights->snapshot($user);
        $this->conversations->recordUserMessage($conversation, $user, $question);

        $provider = (string) config('ai.provider', 'generic');
        $agentProviders = ['groq', 'openrouter', 'openai_compatible'];
        $result = null;

        if (
            config('ai.enabled', false)
            && in_array($provider, $agentProviders, true)
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
                    'fallback_reason' => $agentResult['error_code'] ?? 'provider_error',
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

        $assistant = $this->conversations->recordAssistantMessage(
            $conversation,
            $result,
        );

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
            ->map(fn ($value, $currency) => $currency.' '.number_format((float) $value, 2))
            ->implode(', ');
    }
}
