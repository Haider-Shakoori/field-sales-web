<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AiUsageService
{
    public function __construct(
        private readonly TenantClock $clock,
    ) {}

    public function dashboard(User $user, array $filters = []): array
    {
        $user->loadMissing('tenant');
        $timezone = $this->clock->timezone($user->tenant);
        $now = CarbonImmutable::now($timezone);

        $from = $this->date(
            $filters['date_from'] ?? null,
            $now->subDays(29)->startOfDay(),
            $timezone,
        );
        $to = $this->date(
            $filters['date_to'] ?? null,
            $now->endOfDay(),
            $timezone,
        )->endOfDay();

        if ($from->gt($to) || $from->diffInDays($to) > 90) {
            $from = $now->subDays(29)->startOfDay();
            $to = $now->endOfDay();
        }

        $fromUtc = $from->utc();
        $toUtc = $to->utc();
        $base = $this->assistantQuery($fromUtc, $toUtc);

        $responses = (clone $base)->count();
        $connected = (clone $base)
            ->where('meta->provider_status', 'connected')
            ->count();
        $fallback = (clone $base)
            ->where('meta->provider_status', 'fallback')
            ->count();
        $local = (clone $base)
            ->where('meta->provider_status', 'local')
            ->count();

        $promptTokens = (int) ((clone $base)->sum('prompt_tokens') ?? 0);
        $completionTokens = (int) ((clone $base)->sum('completion_tokens') ?? 0);
        $toolCalls = (int) ((clone $base)->sum('tool_call_count') ?? 0);
        $estimatedCost = (float) ((clone $base)->sum('estimated_cost_usd') ?? 0);
        $averageLatency = (clone $base)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        $conversationCount = (clone $base)
            ->distinct('conversation_id')
            ->count('conversation_id');

        $userCount = AiConversation::query()
            ->whereHas('messages', fn (Builder $query) => $query
                ->where('role', 'assistant')
                ->whereBetween('created_at', [$fromUtc, $toUtc]))
            ->distinct('user_id')
            ->count('user_id');

        $providerAttempts = $connected + $fallback;
        $providerSuccessRate = $providerAttempts > 0
            ? round(($connected / $providerAttempts) * 100, 1)
            : null;

        [$fallbackReasons, $toolUsage] = $this->metaBreakdown(
            $fromUtc,
            $toUtc,
        );

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'timezone' => $timezone,
            ],
            'summary' => [
                'responses' => $responses,
                'conversations' => $conversationCount,
                'users' => $userCount,
                'connected' => $connected,
                'fallback' => $fallback,
                'local' => $local,
                'provider_success_rate' => $providerSuccessRate,
                'average_latency_ms' => $averageLatency === null
                    ? null
                    : (int) round((float) $averageLatency),
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'tool_calls' => $toolCalls,
                'estimated_cost_usd' => round($estimatedCost, 8),
                'cost_configured' => $this->costConfigured(),
            ],
            'providers' => $this->providerBreakdown($fromUtc, $toUtc),
            'fallback_reasons' => $fallbackReasons,
            'tools' => $toolUsage,
            'daily' => $this->dailyBreakdown($fromUtc, $toUtc, $timezone),
            'recent' => $this->recent(
                $user,
                $fromUtc,
                $toUtc,
                $user->hasPermission('audit:view'),
            ),
            'can_view_prompts' => $user->hasPermission('audit:view'),
        ];
    }

    private function assistantQuery(
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): Builder {
        return AiMessage::query()
            ->where('role', 'assistant')
            ->whereBetween('created_at', [$fromUtc, $toUtc]);
    }

    private function providerBreakdown(
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): Collection {
        return $this->assistantQuery($fromUtc, $toUtc)
            ->selectRaw(
                'provider, model, COUNT(*) as responses,
                SUM(prompt_tokens) as prompt_tokens,
                SUM(completion_tokens) as completion_tokens,
                SUM(tool_call_count) as tool_calls,
                SUM(estimated_cost_usd) as estimated_cost_usd,
                AVG(latency_ms) as average_latency_ms'
            )
            ->groupBy('provider', 'model')
            ->orderByDesc('responses')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider ?: 'local',
                'model' => $row->model,
                'responses' => (int) $row->responses,
                'prompt_tokens' => (int) ($row->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($row->completion_tokens ?? 0),
                'tool_calls' => (int) ($row->tool_calls ?? 0),
                'estimated_cost_usd' => round(
                    (float) ($row->estimated_cost_usd ?? 0),
                    8,
                ),
                'average_latency_ms' => $row->average_latency_ms === null
                    ? null
                    : (int) round((float) $row->average_latency_ms),
            ]);
    }

    private function metaBreakdown(
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): array {
        $fallbackReasons = [];
        $toolUsage = [];

        $this->assistantQuery($fromUtc, $toUtc)
            ->select(['id', 'meta'])
            ->chunkById(500, function ($messages) use (
                &$fallbackReasons,
                &$toolUsage,
            ): void {
                foreach ($messages as $message) {
                    $reason = data_get($message->meta, 'fallback_reason');

                    if (is_string($reason) && $reason !== '') {
                        $fallbackReasons[$reason] = ($fallbackReasons[$reason] ?? 0) + 1;
                    }

                    $tools = data_get($message->meta, 'tools_used', []);

                    if (! is_array($tools)) {
                        continue;
                    }

                    foreach ($tools as $tool) {
                        if (! is_string($tool) || $tool === '') {
                            continue;
                        }

                        $toolUsage[$tool] = ($toolUsage[$tool] ?? 0) + 1;
                    }
                }
            });

        arsort($fallbackReasons);
        arsort($toolUsage);

        return [
            collect($fallbackReasons)
                ->map(fn (int $count, string $reason) => [
                    'reason' => $reason,
                    'count' => $count,
                ])
                ->values(),
            collect($toolUsage)
                ->map(fn (int $count, string $tool) => [
                    'tool' => $tool,
                    'count' => $count,
                ])
                ->values(),
        ];
    }

    private function dailyBreakdown(
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
        string $timezone,
    ): Collection {
        $daily = [];

        $this->assistantQuery($fromUtc, $toUtc)
            ->select([
                'id',
                'created_at',
                'prompt_tokens',
                'completion_tokens',
                'estimated_cost_usd',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($messages) use (&$daily, $timezone): void {
                foreach ($messages as $message) {
                    $day = $message->created_at
                        ->copy()
                        ->setTimezone($timezone)
                        ->toDateString();

                    $daily[$day] ??= [
                        'date' => $day,
                        'responses' => 0,
                        'tokens' => 0,
                        'estimated_cost_usd' => 0.0,
                    ];

                    $daily[$day]['responses']++;
                    $daily[$day]['tokens'] +=
                        (int) ($message->prompt_tokens ?? 0)
                        + (int) ($message->completion_tokens ?? 0);
                    $daily[$day]['estimated_cost_usd'] +=
                        (float) ($message->estimated_cost_usd ?? 0);
                }
            });

        ksort($daily);

        return collect($daily)->map(fn (array $row) => [
            ...$row,
            'estimated_cost_usd' => round(
                (float) $row['estimated_cost_usd'],
                8,
            ),
        ])->values();
    }

    private function recent(
        User $user,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
        bool $canViewPrompts,
    ): Collection {
        return $this->assistantQuery($fromUtc, $toUtc)
            ->with([
                'conversation.user',
                'conversation.messages',
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (AiMessage $message) use (
                $user,
                $canViewPrompts,
            ): array {
                $question = $message->conversation?->messages
                    ?->where('role', 'user')
                    ->where('id', '<', $message->id)
                    ->last();

                return [
                    'created_at' => $message->created_at,
                    'conversation_uuid' => $message->conversation?->uuid,
                    'conversation_title' => $canViewPrompts
                        ? $message->conversation?->title
                        : null,
                    'user' => $canViewPrompts
                        ? $message->conversation?->user?->name
                        : null,
                    'question' => $canViewPrompts
                        ? $question?->content
                        : null,
                    'source' => $message->source,
                    'provider' => $message->provider,
                    'model' => $message->model,
                    'provider_status' => data_get(
                        $message->meta,
                        'provider_status',
                    ),
                    'fallback_reason' => data_get(
                        $message->meta,
                        'fallback_reason',
                    ),
                    'latency_ms' => $message->latency_ms,
                    'prompt_tokens' => $message->prompt_tokens,
                    'completion_tokens' => $message->completion_tokens,
                    'tool_call_count' => $message->tool_call_count,
                    'tools_used' => data_get(
                        $message->meta,
                        'tools_used',
                        [],
                    ),
                    'estimated_cost_usd' => $message->estimated_cost_usd,
                    'provider_http_status' => $message->provider_http_status,
                    'provider_request_id' => $canViewPrompts
                        ? $message->provider_request_id
                        : null,
                ];
            });
    }

    private function costConfigured(): bool
    {
        return config('ai.input_cost_per_million') !== null
            && config('ai.input_cost_per_million') !== ''
            && config('ai.output_cost_per_million') !== null
            && config('ai.output_cost_per_million') !== '';
    }

    private function date(
        ?string $value,
        CarbonImmutable $fallback,
        string $timezone,
    ): CarbonImmutable {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                trim($value),
                $timezone,
            )->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
