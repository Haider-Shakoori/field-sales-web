<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AiConversationService;
use App\Services\AiInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiInsightsController extends Controller
{
    public function index(
        Request $request,
        AiInsightsService $insights,
        AiConversationService $conversations,
    ): View {
        $user = $request->user();
        $conversation = $conversations->resolve(
            $user,
            $request->query('conversation'),
        );

        return view('admin.ai-insights.index', [
            'snapshot' => $insights->snapshot($user),
            'conversations' => $conversations->listFor($user),
            'conversation' => $conversation,
            'messages' => $conversation
                ? $conversations->messages($conversation)
                : collect(),
            'providerEnabled' => $this->providerEnabled(),
            'providerName' => (string) config('ai.provider', 'generic'),
            'providerModel' => (string) config('ai.model', ''),
            'customerDataEnabled' => (bool) config('ai.allow_customer_data', false),
            'suggestedPrompts' => $this->suggestedPrompts(),
        ]);
    }

    public function ask(
        Request $request,
        AiInsightsService $insights,
        AiConversationService $conversations,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'conversation_uuid' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        $conversation = $conversations->resolve(
            $user,
            $validated['conversation_uuid'] ?? null,
        );

        if (! $conversation) {
            $conversation = $conversations->create(
                $user,
                $validated['question'],
            );
        }

        $history = $conversations->context($conversation);
        $conversations->appendUser(
            $conversation,
            $user,
            $validated['question'],
        );

        $result = $insights->answer(
            $user,
            $validated['question'],
            $history,
        );

        $assistant = $conversations->appendAssistant(
            $conversation,
            $result,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'answer' => $result['answer'],
                'source' => $result['source'],
                'provider_status' => $result['provider_status'] ?? 'local',
                'fallback_reason' => $result['fallback_reason'] ?? null,
                'fallback_message' => $result['fallback_message'] ?? null,
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
                'latency_ms' => $result['latency_ms'] ?? null,
                'tools_used' => $result['tools_used'] ?? [],
                'conversation' => [
                    'uuid' => $conversation->uuid,
                    'title' => $conversation->title,
                ],
                'message' => [
                    'uuid' => $assistant->uuid,
                    'created_at' => $assistant->created_at?->toISOString(),
                ],
            ]);
        }

        return redirect(
            route('admin.ai-insights.index', [
                'conversation' => $conversation->uuid,
            ]).'#ask-fieldpulse-bottom'
        );
    }

    public function archive(
        Request $request,
        string $conversation,
        AiConversationService $conversations,
    ): RedirectResponse {
        $thread = $conversations->resolve(
            $request->user(),
            $conversation,
        );

        if ($thread) {
            $conversations->archive($thread);
        }

        return redirect()
            ->route('admin.ai-insights.index')
            ->with('status', __('Conversation archived.'));
    }

    private function providerEnabled(): bool
    {
        if (! config('ai.enabled', false)) {
            return false;
        }

        $provider = (string) config('ai.provider', 'generic');

        if (in_array($provider, ['groq', 'openrouter', 'openai_compatible'], true)) {
            return trim((string) config('ai.api_key')) !== ''
                && trim((string) config('ai.model')) !== '';
        }

        return trim((string) config('ai.endpoint')) !== '';
    }

    private function suggestedPrompts(): array
    {
        $prompts = [
            __('What needs my attention today?'),
            __('Who sold the most this month?'),
            __('Compare sales and collections for the last 30 days.'),
            __('Which salesmen have not started work today?'),
        ];

        if (config('ai.allow_customer_data', false)) {
            $prompts[] = __('Which customers owe us the most?');
            $prompts[] = __('Which customers have not been visited in 30 days?');
            $prompts[] = __('Show overdue high-priority follow-ups.');
        }

        return $prompts;
    }
}
