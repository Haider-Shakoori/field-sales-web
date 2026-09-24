<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiInsightsAgentService
{
    public function __construct(
        private readonly AiInsightToolService $tools,
    ) {}

    public function answer(
        User $user,
        string $question,
        array $snapshot,
        array $history = [],
    ): array {
        $startedAt = microtime(true);
        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));
        $model = trim((string) config('ai.model'));
        $provider = (string) config('ai.provider', 'generic');

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return $this->failure(
                'not_configured',
                'The external AI provider is not fully configured.',
                $provider,
                $model,
                $startedAt,
            );
        }

        $toolDefinitions = $this->tools->definitions($user);
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($user, $snapshot),
            ],
            ...$this->normalizeHistory($history),
            [
                'role' => 'user',
                'content' => trim($question),
            ],
        ];
        $toolActivity = [];
        $usage = [];
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

                if (! $response->successful()) {
                    return $this->httpFailure(
                        $response,
                        $provider,
                        $model,
                        $startedAt,
                        $toolActivity,
                    );
                }

                $usage = is_array($response->json('usage'))
                    ? $response->json('usage')
                    : $usage;
                $message = $response->json('choices.0.message');

                if (! is_array($message)) {
                    return $this->failure(
                        'invalid_response',
                        'The AI provider returned an invalid response.',
                        $provider,
                        $model,
                        $startedAt,
                        $toolActivity,
                    );
                }

                $toolCalls = $message['tool_calls'] ?? [];

                if (! is_array($toolCalls) || $toolCalls === []) {
                    $answer = $message['content'] ?? null;

                    if (! is_string($answer) || trim($answer) === '') {
                        return $this->failure(
                            'empty_response',
                            'The AI provider returned an empty answer.',
                            $provider,
                            $model,
                            $startedAt,
                            $toolActivity,
                        );
                    }

                    return [
                        'ok' => true,
                        'answer' => trim($answer),
                        'provider' => $provider,
                        'model' => $model,
                        'tool_activity' => $toolActivity,
                        'usage' => $usage,
                        'latency_ms' => $this->latency($startedAt),
                        'error_code' => null,
                        'error_message' => null,
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
                            'The AI provider returned an invalid FieldPulse tool request.',
                            $provider,
                            $model,
                            $startedAt,
                            $toolActivity,
                        );
                    }

                    $activity = [
                        'name' => $name,
                        'label' => $this->tools->activityLabel($name, $arguments),
                        'status' => 'success',
                    ];

                    try {
                        $result = $this->tools->execute(
                            $user,
                            $name,
                            $arguments,
                        );
                    } catch (Throwable $exception) {
                        $activity['status'] = 'error';
                        $result = [
                            'error' => class_basename($exception),
                            'message' => 'FieldPulse could not complete this read-only data lookup.',
                        ];
                    }

                    $toolActivity[] = $activity;

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

            return $this->failure(
                'tool_round_limit',
                'The AI reached its FieldPulse lookup limit before producing an answer.',
                $provider,
                $model,
                $startedAt,
                $toolActivity,
            );
        } catch (Throwable) {
            return $this->failure(
                'connection_error',
                'The AI provider could not be reached.',
                $provider,
                $model,
                $startedAt,
                $toolActivity,
            );
        }
    }

    public function checkConnection(): array
    {
        $startedAt = microtime(true);
        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));
        $model = trim((string) config('ai.model'));
        $provider = (string) config('ai.provider', 'generic');

        if (! config('ai.enabled', false) || $baseUrl === '' || $apiKey === '' || $model === '') {
            return [
                'ok' => false,
                'provider' => $provider,
                'model' => $model,
                'status' => 'not_configured',
                'message' => 'External AI is not fully configured.',
                'latency_ms' => $this->latency($startedAt),
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
                $failure = $this->httpFailure(
                    $response,
                    $provider,
                    $model,
                    $startedAt,
                );

                return [
                    'ok' => false,
                    'provider' => $provider,
                    'model' => $model,
                    'status' => $failure['error_code'],
                    'message' => $failure['error_message'],
                    'latency_ms' => $failure['latency_ms'],
                ];
            }

            return [
                'ok' => true,
                'provider' => $provider,
                'model' => $model,
                'status' => 'connected',
                'message' => 'AI provider connection is healthy.',
                'latency_ms' => $this->latency($startedAt),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'provider' => $provider,
                'model' => $model,
                'status' => 'connection_error',
                'message' => 'The AI provider could not be reached.',
                'latency_ms' => $this->latency($startedAt),
            ];
        }
    }

    private function normalizeHistory(array $history): array
    {
        return collect($history)
            ->filter(fn ($message) => is_array($message)
                && in_array($message['role'] ?? null, ['user', 'assistant'], true)
                && is_string($message['content'] ?? null)
                && trim($message['content']) !== '')
            ->map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => trim($message['content']),
            ])
            ->values()
            ->all();
    }

    private function systemPrompt(User $user, array $snapshot): string
    {
        $tenant = $user->loadMissing('tenant')->tenant;
        $customerData = config('ai.allow_customer_data', false)
            && $user->hasPermission('customers:view');

        return implode("\n", [
            'You are Ask FieldPulse, a read-only business intelligence assistant inside a field-sales system.',
            'Answer questions about the current tenant using only the supplied snapshot, conversation context, and tool results.',
            'Use tools whenever an answer depends on current business records, comparisons, rankings, date ranges, customers, products, stock, expenses, returns, attendance, visits, follow-ups, sales, collections, routes, or territories.',
            'Treat earlier user and assistant messages as conversational context, but re-check current business facts with tools when needed.',
            'Never invent numbers, customers, products, salesmen, dates, balances, stock quantities, or business events.',
            'Never claim an action was performed; this assistant is read-only.',
            'If available data cannot answer the question, say exactly what data is missing.',
            'Respect the available tool set: unavailable tools mean the signed-in user is not authorized for that data.',
            'Keep money separated by currency; never add AFN, USD, and PKR together.',
            'Interpret relative dates against the supplied current date and tenant timezone.',
            'Prefer concise business answers, but use short tables or bullets when comparing multiple rows.',
            'Respond in locale '.app()->getLocale().'.',
            'Tenant timezone: '.($tenant?->timezone ?: config('app.timezone', 'UTC')).'.',
            'Customer-level AI tools enabled: '.($customerData ? 'yes' : 'no').'.',
            'Current aggregate snapshot: '.json_encode(
                collect($snapshot)->except('recommendations')->all(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ]);
    }

    private function httpFailure(
        Response $response,
        string $provider,
        string $model,
        float $startedAt,
        array $toolActivity = [],
    ): array {
        $status = $response->status();
        [$code, $message] = match (true) {
            in_array($status, [401, 403], true) => [
                'authentication_failed',
                'The AI provider rejected the configured credentials.',
            ],
            $status === 429 => [
                'rate_limited',
                'The AI provider rate limit has been reached.',
            ],
            $status >= 500 => [
                'provider_unavailable',
                'The AI provider is temporarily unavailable.',
            ],
            default => [
                'provider_error',
                'The AI provider returned an error.',
            ],
        };

        return $this->failure(
            $code,
            $message,
            $provider,
            $model,
            $startedAt,
            $toolActivity,
            $status,
        );
    }

    private function failure(
        string $code,
        string $message,
        string $provider,
        string $model,
        float $startedAt,
        array $toolActivity = [],
        ?int $httpStatus = null,
    ): array {
        return [
            'ok' => false,
            'answer' => null,
            'provider' => $provider,
            'model' => $model,
            'tool_activity' => $toolActivity,
            'usage' => [],
            'latency_ms' => $this->latency($startedAt),
            'error_code' => $code,
            'error_message' => $message,
            'http_status' => $httpStatus,
        ];
    }

    private function latency(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
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
