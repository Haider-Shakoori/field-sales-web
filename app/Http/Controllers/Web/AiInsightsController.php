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
        ]);
    }

    public function ask(
        Request $request,
        AiInsightsService $insights,
    ): View|JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $result = $insights->answer(
            $request->user(),
            $validated['question'],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'answer' => $result['answer'],
                'source' => $result['source'],
            ]);
        }

        return redirect(
            route('admin.ai-insights.index').'#ask-fieldpulse-answer'
        )
            ->with('ai_question', $validated['question'])
            ->with('ai_answer', $result['answer'])
            ->with('ai_answer_source', $result['source']);
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
