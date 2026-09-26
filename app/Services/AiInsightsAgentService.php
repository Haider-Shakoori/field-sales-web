<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiInsightsAgentService
{
    public function __construct(
        private readonly AiInsightToolService $tools,
        private readonly AiPolicyService $policy,
    ) {}

    public function answer(
        User $user,
        string $question,
        array $snapshot,
        array $history = [],
    ): array {
        $startedAt = hrtime(true);

        if (! $this->policy->externalEnabled($user)) {
            return $this->failure(
                'tenant_ai_disabled',
                'External AI is disabled for this organization.',
                $startedAt,
            );
        }

        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));
        $model = trim((string) config('ai.model'));

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return $this->failure(
                'provider_not_configured',
                'The external AI provider is not fully configured.',
                $startedAt,
            );
        }

        $toolDefinitions = $this->tools->definitions($user);
        $messages = [[
            'role' => 'system',
            'content' => $this->systemPrompt($user, $snapshot),
        ]];

        foreach (array_slice($history, -16) as $historyMessage) {
            $role = $historyMessage['role'] ?? null;
            $content = trim((string) ($historyMessage['content'] ?? ''));

            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($question),
        ];
        $toolsUsed = [];
        $promptTokens = 0;
        $completionTokens = 0;
        $providerHttpStatus = null;
        $providerRequestId = null;

        $maxRounds = min(6, max(1, (int) config('ai.max_tool_rounds', 4)));

        try {
            for ($round = 0; $round < $maxRounds; $round++) {
                $response = Http::timeout(
                    max(5, (int) config('ai.timeout_seconds', 20)),
                )
                    ->acceptJson()
                    ->withToken($apiKey)
                    ->post($baseUrl.'/chat/completions', [
                        'model' => $model,
                        'messages' => $messages,
                        'tools' => $toolDefinitions,
                        'tool_choice' => 'auto',
                        'temperature' => 0.1,
                    ]);

                $providerHttpStatus = $response->status();
                $providerRequestId = $this->requestId($response);
                $promptTokens += (int) ($response->json('usage.prompt_tokens') ?? 0);
                $completionTokens += (int) ($response->json('usage.completion_tokens') ?? 0);

                if (! $response->successful()) {
                    return $this->failure(
                        'http_'.$response->status(),
                        $this->providerErrorMessage($response->status()),
                        $startedAt,
                        $providerHttpStatus,
                        $providerRequestId,
                        $promptTokens,
                        $completionTokens,
                        $toolsUsed,
                    );
                }

                $message = $response->json('choices.0.message');

                if (! is_array($message)) {
                    return $this->failure(
                        'invalid_provider_response',
                        'The AI provider returned an invalid response.',
                        $startedAt,
                        $providerHttpStatus,
                        $providerRequestId,
                        $promptTokens,
                        $completionTokens,
                        $toolsUsed,
                    );
                }

                $toolCalls = $message['tool_calls'] ?? [];

                if (! is_array($toolCalls) || $toolCalls === []) {
                    $content = $message['content'] ?? null;

                    if (! is_string($content) || trim($content) === '') {
                        return $this->failure(
                            'empty_provider_response',
                            'The AI provider returned an empty answer.',
                            $startedAt,
                            $providerHttpStatus,
                            $providerRequestId,
                            $promptTokens,
                            $completionTokens,
                            $toolsUsed,
                        );
                    }

                    return [
                        'ok' => true,
                        'answer' => trim($content),
                        'provider' => (string) config('ai.provider'),
                        'model' => $model,
                        'provider_status' => 'connected',
                        'latency_ms' => $this->elapsedMs($startedAt),
                        'provider_http_status' => $providerHttpStatus,
                        'provider_request_id' => $providerRequestId,
                        'usage' => [
                            'prompt_tokens' => $promptTokens,
                            'completion_tokens' => $completionTokens,
                        ],
                        'tool_call_count' => count($toolsUsed),
                        'tools_used' => array_values(array_unique($toolsUsed)),
                    ];
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $toolCall) {
                    $toolId = (string) ($toolCall['id'] ?? '');
                    $function = $toolCall['function'] ?? [];
                    $name = (string) ($function['name'] ?? '');
                    $arguments = json_decode(
                        (string) ($function['arguments'] ?? '{}'),
                        true,
                    );

                    if ($toolId === '' || $name === '' || ! is_array($arguments)) {
                        return $this->failure(
                            'invalid_tool_call',
                            'The AI provider returned an invalid tool request.',
                            $startedAt,
                            $providerHttpStatus,
                            $providerRequestId,
                            $promptTokens,
                            $completionTokens,
                            $toolsUsed,
                        );
                    }

                    $toolsUsed[] = $name;

                    try {
                        $result = $this->tools->execute(
                            $user,
                            $name,
                            $arguments,
                        );
                    } catch (Throwable $exception) {
                        $result = [
                            'error' => class_basename($exception),
                            'message' => $exception->getMessage(),
                        ];
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolId,
                        'name' => $name,
                        'content' => json_encode(
                            $result,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                                | JSON_THROW_ON_ERROR,
                        ),
                    ];
                }
            }
        } catch (Throwable) {
            return $this->failure(
                'provider_connection_failed',
                'The AI provider could not be reached.',
                $startedAt,
                $providerHttpStatus,
                $providerRequestId,
                $promptTokens,
                $completionTokens,
                $toolsUsed,
            );
        }

        return $this->failure(
            'tool_round_limit',
            'The AI provider reached the maximum tool-call rounds.',
            $startedAt,
            $providerHttpStatus,
            $providerRequestId,
            $promptTokens,
            $completionTokens,
            $toolsUsed,
        );
    }

    public function health(User $user): array
    {
        $startedAt = hrtime(true);
        $provider = (string) config('ai.provider', 'generic');
        $model = trim((string) config('ai.model'));
        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));

        if (! $this->policy->externalEnabled($user)) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'provider' => $provider,
                'model' => $model,
                'message' => 'External AI is disabled.',
                'latency_ms' => 0,
            ];
        }

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return [
                'ok' => false,
                'status' => 'not_configured',
                'provider' => $provider,
                'model' => $model,
                'message' => 'External AI is not fully configured.',
                'latency_ms' => 0,
            ];
        }

        try {
            $response = Http::timeout(
                max(5, min(15, (int) config('ai.timeout_seconds', 20))),
            )
                ->acceptJson()
                ->withToken($apiKey)
                ->get($baseUrl.'/models');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => 'http_'.$response->status(),
                    'provider' => $provider,
                    'model' => $model,
                    'message' => $this->providerErrorMessage($response->status()),
                    'latency_ms' => $this->elapsedMs($startedAt),
                ];
            }

            return [
                'ok' => true,
                'status' => 'connected',
                'provider' => $provider,
                'model' => $model,
                'message' => 'AI provider connection is healthy.',
                'latency_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'connection_failed',
                'provider' => $provider,
                'model' => $model,
                'message' => 'The AI provider could not be reached.',
                'latency_ms' => $this->elapsedMs($startedAt),
            ];
        }
    }

    private function systemPrompt(User $user, array $snapshot): string
    {
        $tenant = $user->loadMissing('tenant')->tenant;
        $customerData = $this->policy->customerDataEnabled($user)
            && $user->hasPermission('customers:view');

        return implode("\n", [
            'You are Ask FieldPulse, a read-only business intelligence assistant inside a field-sales system.',
            'Answer questions about the current tenant using only the supplied snapshot and tool results.',
            'Use tools whenever the answer depends on business records, comparisons, rankings, date ranges, customer balances, attendance, visits, follow-ups, sales, collections, expenses, stock, returns, products, scorecards, mileage, fuel efficiency, or management priorities.',
            'For travel distance, mileage, fuel use, fuel cost, km per liter, or GPS-versus-odometer variance, use get_mileage_summary.',
            'For "what needs my attention", risks, priorities, declining sales, reorder opportunities, overdue receivables, stale coverage, pending approvals, or suspicious activity, prefer get_recommendations.',
            'For a morning briefing, daily management summary, or "brief me", prefer get_manager_briefing.',
            'For route progress, missed planned visits, off-route activity, route capacity, or who is behind today, use get_route_execution.',
            'For territory comparisons, coverage gaps, stale customers by territory, customer density, unassigned customers, GPS points outside assigned polygons, or geographic management questions, use get_territory_performance.',
            'Response style: write polished, human-readable Markdown like a strong business analyst, not like an API response.',
            'Lead with the direct answer or conclusion. Do not begin with generic filler such as "Based on the data".',
            'Use short paragraphs. Use 1-3 descriptive headings only when they improve a multi-part answer.',
            'Use bullet points for actions or multiple findings. Use a Markdown table only for a genuine comparison with several rows or columns.',
            'Bold the most important figures, names, or exceptions. Keep labels natural, for example "Completed visits" instead of "visited_planned_stops".',
            'When giving management analysis, prefer this flow when useful: concise conclusion, key figures, what needs attention, practical next actions.',
            'Never expose raw JSON, database field names, internal tool payloads, UUIDs, internal IDs, stack traces, or tool-call mechanics unless the user explicitly asks for technical diagnostics.',
            'Do not repeat the same number in several sections. Be concise but complete.',
            'Never invent numbers, customers, salesmen, dates, balances, or business events.',
            'Never claim an action was performed; this assistant is read-only.',
            'If available data cannot answer the question, say exactly what data is missing.',
            'Respect the tool set: unavailable tools mean the signed-in user is not authorized for that data.',
            'Keep money separated by currency; never add AFN, USD, and PKR together.',
            'Interpret relative dates against the supplied current date and tenant timezone.',
            'Respond in locale '.app()->getLocale().'.',
            'Tenant timezone: '.($tenant?->timezone ?: config('app.timezone', 'UTC')).'.',
            'Customer-level AI tools enabled: '.($customerData ? 'yes' : 'no').'.',
            'Current aggregate snapshot: '.json_encode(
                collect($snapshot)->except('recommendations')->all(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ]);
    }

    private function failure(
        string $reason,
        string $message,
        int $startedAt,
        ?int $providerHttpStatus = null,
        ?string $providerRequestId = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        array $toolsUsed = [],
    ): array {
        return [
            'ok' => false,
            'fallback_reason' => $reason,
            'fallback_message' => $message,
            'provider' => (string) config('ai.provider'),
            'model' => (string) config('ai.model'),
            'provider_status' => 'fallback',
            'provider_http_status' => $providerHttpStatus,
            'provider_request_id' => $providerRequestId,
            'latency_ms' => $this->elapsedMs($startedAt),
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
            ],
            'tool_call_count' => count($toolsUsed),
            'tools_used' => array_values(array_unique($toolsUsed)),
        ];
    }

    private function requestId($response): ?string
    {
        foreach ([
            'x-request-id',
            'x-groq-request-id',
            'request-id',
        ] as $header) {
            $value = $response->header($header);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function providerErrorMessage(int $status): string
    {
        return match ($status) {
            401, 403 => 'The AI provider rejected the configured credentials.',
            429 => 'The AI provider rate limit has been reached.',
            408, 504 => 'The AI provider timed out.',
            default => 'The AI provider returned HTTP '.$status.'.',
        };
    }

    private function baseUrl(): string
    {
        $configured = rtrim(trim((string) config('ai.base_url')), '/');

        if ($configured !== '') {
            return $configured;
        }

        return match ((string) config('ai.provider')) {
            'groq' => 'https://api.groq.com/openai/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            default => '',
        };
    }
}
