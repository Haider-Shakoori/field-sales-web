<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
    ): View {
        return view('admin.ai-insights.index', [
            'snapshot' => $insights->snapshot($request->user()),
            'question' => session('ai_question'),
            'answer' => session('ai_answer'),
            'answerSource' => session('ai_answer_source'),
            'providerEnabled' => $this->providerEnabled(),
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
