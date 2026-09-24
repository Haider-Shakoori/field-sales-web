<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AiConversationService;
use App\Services\AiInsightsAgentService;
use App\Services\AiInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiInsightsController extends Controller
{
    // Ask FieldPulse web experience.
    public function index(
        Request $request,
        AiInsightsService $insights,
        AiConversationService $conversations,
    ): View {
        $user = $request->user();
        $selected = null;
        $conversationUuid = trim((string) $request->string('chat'));

        if ($conversationUuid !== '') {
            $selected = $conversations->findOwned($user, $conversationUuid)
                ->load('messages');
        }

        return view('admin.ai-insights.index', [
            'snapshot' => $insights->snapshot($user),
            'providerEnabled' => $this->providerEnabled(),
            'provider' => (string) config('ai.provider', 'generic'),
            'model' => (string) config('ai.model', ''),
            'customerDataEnabled' => (bool) config('ai.allow_customer_data', false),
            'historyRetentionDays' => max(
                0,
                (int) config('ai.history_retention_days', 90),
            ),
            'conversations' => $conversations->recent($user),
            'archivedConversations' => $conversations->archived($user),
            'selectedConversation' => $selected
                ? $conversations->serializeConversation($selected)
                : null,
            'question' => null,
            'answer' => null,
            'answerSource' => null,
        ]);
    }

    public function ask(
        Request $request,
        AiConversationService $conversations,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $result = $conversations->ask(
            $request->user(),
            $validated['question'],
            $validated['conversation_id'] ?? null,
        );
        $conversation = $result['conversation'];

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'uuid' => $conversation->uuid,
                    'title' => $conversation->title,
                ],
                'message' => [
                    'uuid' => $result['message']->uuid,
                    'content' => $result['answer'],
                    'source' => $result['source'],
                    'provider' => $result['provider'],
                    'model' => $result['model'],
                    'fallback_reason' => $result['fallback_reason'],
                    'provider_status' => $result['provider_status'],
                    'tool_activity' => $result['tool_activity'],
                    'latency_ms' => $result['latency_ms'],
                ],
            ]);
        }

        return redirect()->route(
            'admin.ai-insights.index',
            ['chat' => $conversation->uuid],
        );
    }

    public function providerCheck(
        Request $request,
        AiInsightsAgentService $agent,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('reports:view'), 403);

        return response()->json($agent->checkConnection());
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
}
