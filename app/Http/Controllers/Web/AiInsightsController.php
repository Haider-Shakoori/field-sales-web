<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AiInsightsService;
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
            'question' => null,
            'answer' => null,
            'answerSource' => null,
            'providerEnabled' => (bool) config('ai.enabled', false)
                && trim((string) config('ai.endpoint')) !== '',
        ]);
    }

    public function ask(
        Request $request,
        AiInsightsService $insights,
    ): View {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $result = $insights->answer(
            $request->user(),
            $validated['question'],
        );

        return view('admin.ai-insights.index', [
            'snapshot' => $result['snapshot'],
            'question' => $validated['question'],
            'answer' => $result['answer'],
            'answerSource' => $result['source'],
            'providerEnabled' => (bool) config('ai.enabled', false)
                && trim((string) config('ai.endpoint')) !== '',
        ]);
    }
}
