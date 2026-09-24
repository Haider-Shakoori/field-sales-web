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
    ): ?string {
        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));
        $model = trim((string) config('ai.model'));

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return null;
        }

        $toolDefinitions = $this->tools->definitions($user);
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($user, $snapshot),
            ],
            [
                'role' => 'user',
                'content' => trim($question),
            ],
        ];

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
                    return null;
                }

                $message = $response->json('choices.0.message');

                if (! is_array($message)) {
                    return null;
                }

                $toolCalls = $message['tool_calls'] ?? [];

                if (! is_array($toolCalls) || $toolCalls === []) {
                    $content = $message['content'] ?? null;

                    return is_string($content) && trim($content) !== ''
                        ? trim($content)
                        : null;
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
                        return null;
                    }

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
            return null;
        }

        return null;
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
