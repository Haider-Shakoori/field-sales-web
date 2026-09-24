<x-layouts.app>
<div class="grid gap-6 xl:grid-cols-[290px_minmax(0,1fr)]">
    <aside class="xl:sticky xl:top-6 xl:self-start">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-400 font-black text-white">F</span>
                <div>
                    <p class="font-semibold text-white">{{ __('Ask FieldPulse') }}</p>
                    <p class="text-xs text-slate-500">{{ __('Conversation history') }}</p>
                </div>
            </div>
            <a href="{{ route('admin.ai-insights.index') }}" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400">
                <span class="text-lg">＋</span> {{ __('New chat') }}
            </a>
        </div>
    </aside>
    <main class="min-w-0">
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">{{ __('AI insights') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Grounded recommendations and manager Q&A from FieldPulse operational data.') }}</p>
    </div>
    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $providerEnabled ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-800 text-slate-300' }}">
        {{ $providerEnabled ? __('AI provider connected') : __('Grounded local mode') }}
    </span>
</div>

<section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Visits today') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ $snapshot['visits_today'] }}</p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Overdue follow-ups') }}</p>
        <p class="mt-2 text-3xl font-bold {{ $snapshot['overdue_followups'] > 0 ? 'text-rose-300' : '' }}">{{ $snapshot['overdue_followups'] }}</p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Customers not visited in 30 days') }}</p>
        <p class="mt-2 text-3xl font-bold {{ $snapshot['customers_not_visited_30_days'] > 0 ? 'text-amber-300' : '' }}">{{ $snapshot['customers_not_visited_30_days'] }}</p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Salesmen started today') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ $snapshot['salesmen_started_today'] }} / {{ $snapshot['active_salesmen'] }}</p>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <div class="mb-4">
            <h2 class="text-lg font-semibold">{{ __('Recommended actions') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Generated from overdue work, visit coverage, approvals and attendance exceptions.') }}</p>
        </div>
        <div class="space-y-3">
            @foreach($snapshot['recommendations'] as $recommendation)
                @php
                    $tone = match($recommendation['severity']) {
                        'high' => 'border-rose-400/20 bg-rose-500/10',
                        'medium' => 'border-amber-400/20 bg-amber-500/10',
                        default => 'border-emerald-400/20 bg-emerald-500/10',
                    };
                @endphp
                <article class="rounded-xl border p-4 {{ $tone }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold">{{ __($recommendation['title']) }}</h3>
                            <p class="mt-1 text-sm text-slate-200">{{ __($recommendation['message'], $recommendation['message_params'] ?? []) }}</p>
                            <p class="mt-2 text-sm text-slate-400">{{ __($recommendation['action']) }}</p>
                        </div>
                        <span class="rounded-full bg-black/20 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide">{{ __(str($recommendation['severity'])->title()->toString()) }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('Ask FieldPulse') }}</h2>
        <p class="mt-1 text-sm text-slate-400">{{ __('Ask about sales, collections, visits, follow-ups or field attendance.') }}</p>

        <form id="ask-fieldpulse-form" method="POST" action="{{ route('admin.ai-insights.ask') }}" class="mt-4 space-y-3">
            @csrf
            <textarea name="question" rows="4" maxlength="500" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" placeholder="{{ __('Example: How is customer coverage looking today?') }}">{{ old('question', $question) }}</textarea>
            <button id="ask-fieldpulse-button" class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold disabled:cursor-not-allowed disabled:opacity-60">{{ __('Ask FieldPulse') }}</button>
            <p id="ask-fieldpulse-status" class="hidden text-sm text-slate-400" aria-live="polite"></p>
        </form>

        <div id="ask-fieldpulse-answer" class="mt-5 {{ $answer ? '' : 'hidden' }} rounded-xl border border-indigo-400/20 bg-indigo-500/10 p-4" aria-live="polite">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-300">{{ __('Answer') }}</p>
            <p id="ask-fieldpulse-answer-text" class="mt-2 whitespace-pre-line text-sm leading-6">{{ $answer }}</p>
            <p id="ask-fieldpulse-answer-source" class="mt-3 text-xs text-slate-500">
                @if($answer)
                    {{ in_array($answerSource, ['configured_ai_provider', 'configured_ai_agent'], true) ? __('Generated by the connected AI provider using permission-aware FieldPulse data tools.') : __('Generated locally from FieldPulse metrics without an external AI provider.') }}
                @endif
            </p>
        </div>

        <div class="mt-5 border-t border-white/10 pt-4 text-xs text-slate-500">
            {{ __('External AI uses permission-aware read-only tools. Customer-level data is disabled by default and requires explicit configuration.') }}
        </div>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-white/10 bg-slate-900 p-5">
    <h2 class="text-lg font-semibold">{{ __('7-day commercial pulse') }}</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-xl bg-slate-950 p-4">
            <p class="text-sm text-slate-400">{{ __('Approved sales') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @forelse($snapshot['approved_sales_7d'] as $currency => $amount)
                    <span class="rounded-lg bg-white/5 px-3 py-2 font-semibold">{{ $currency }} {{ number_format($amount, 2) }}</span>
                @empty
                    <span class="text-sm text-slate-500">{{ __('No approved sales recorded.') }}</span>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl bg-slate-950 p-4">
            <p class="text-sm text-slate-400">{{ __('Verified collections') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @forelse($snapshot['verified_collections_7d'] as $currency => $amount)
                    <span class="rounded-lg bg-white/5 px-3 py-2 font-semibold">{{ $currency }} {{ number_format($amount, 2) }}</span>
                @empty
                    <span class="text-sm text-slate-500">{{ __('No verified collections recorded.') }}</span>
                @endforelse
            </div>
        </div>
    </div>
</section>
    </main>
</div>

<script>
(() => {
    const form = document.getElementById('ask-fieldpulse-form');
    if (!form || !window.fetch) return;

    const button = document.getElementById('ask-fieldpulse-button');
    const status = document.getElementById('ask-fieldpulse-status');
    const answerBox = document.getElementById('ask-fieldpulse-answer');
    const answerText = document.getElementById('ask-fieldpulse-answer-text');
    const answerSource = document.getElementById('ask-fieldpulse-answer-source');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        button.disabled = true;
        status.classList.remove('hidden');
        status.textContent = @json(__('Thinking from current FieldPulse data…'));
        answerBox.classList.add('hidden');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();

            if (!response.ok) {
                const firstError = payload?.errors
                    ? Object.values(payload.errors).flat()[0]
                    : payload?.message;

                throw new Error(firstError || @json(__('Ask FieldPulse could not answer right now.')));
            }

            answerText.textContent = payload.answer || @json(__('No answer was returned.'));
            answerSource.textContent = ['configured_ai_provider', 'configured_ai_agent'].includes(payload.source)
                ? @json(__('Generated by the connected AI provider using permission-aware FieldPulse data tools.'))
                : @json(__('Generated locally from FieldPulse metrics without an external AI provider.'));

            answerBox.classList.remove('hidden');
            answerBox.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            status.classList.add('hidden');
        } catch (error) {
            status.textContent = error?.message || @json(__('Ask FieldPulse could not answer right now.'));
        } finally {
            button.disabled = false;
        }
    });
})();
</script>

</x-layouts.app>
