<?php

namespace App\Services;

use App\Models\User;
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
        $startedAt = hrtime(true);
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
                    return $this->failure(
                        'http_'.$response->status(),
                        $this->providerErrorMessage($response->status()),
                        $startedAt,
                    );
                }

                $message = $response->json('choices.0.message');

                if (! is_array($message)) {
                    return $this->failure(
                        'invalid_provider_response',
                        'The AI provider returned an invalid response.',
                        $startedAt,
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
                        );
                    }

                    return [
                        'ok' => true,
                        'answer' => trim($content),
                        'provider' => (string) config('ai.provider'),
                        'model' => $model,
                        'provider_status' => 'connected',
                        'latency_ms' => $this->elapsedMs($startedAt),
                        'usage' => [
                            'prompt_tokens' => $response->json('usage.prompt_tokens'),
                            'completion_tokens' => $response->json('usage.completion_tokens'),
                        ],
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
            );
        }

        return $this->failure(
            'tool_round_limit',
            'The AI provider reached the maximum tool-call rounds.',
            $startedAt,
        );
    }

    private function systemPrompt(User $user, array $snapshot): string
    {
        $tenant = $user->loadMissing('tenant')->tenant;
        $customerData = config('ai.allow_customer_data', false)
            && $user->hasPermission('customers:view');

        return implode("\n", [
            'You are Ask FieldPulse, a read-only business intelligence assistant inside a field-sales system.',
            'Answer questions about the current tenant using only the supplied snapshot and tool results.',
            'Use tools whenever the answer depends on business records, comparisons, rankings, date ranges, customer balances, attendance, visits, follow-ups, sales, or collections.',
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
    ): array {
        return [
            'ok' => false,
            'fallback_reason' => $reason,
            'fallback_message' => $message,
            'provider' => (string) config('ai.provider'),
            'model' => (string) config('ai.model'),
            'provider_status' => 'fallback',
            'latency_ms' => $this->elapsedMs($startedAt),
            'tools_used' => [],
        ];
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
