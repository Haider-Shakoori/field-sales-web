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
            'providerEnabled' => (bool) config('ai.enabled', false)
                && trim((string) config('ai.endpoint')) !== '',
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

        return redirect()
            ->route('admin.ai-insights.index')
            ->withFragment('ask-fieldpulse-answer')
            ->with('ai_question', $validated['question'])
            ->with('ai_answer', $result['answer'])
            ->with('ai_answer_source', $result['source']);
    }
}
