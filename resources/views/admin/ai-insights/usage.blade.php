<x-layouts.app>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">{{ __('AI Usage & Audit') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Monitor Ask FieldPulse usage, provider health, tools, tokens, latency, fallbacks, and estimated cost.') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.ai-insights.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold">{{ __('Back to Ask FieldPulse') }}</a>
        <a href="{{ route('admin.ai-insights.briefing') }}" class="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-200">{{ __('Morning Briefing') }}</a>
    </div>
</div>

<form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 sm:grid-cols-[1fr_1fr_auto]">
    <label class="grid gap-1 text-sm text-slate-300">{{ __('From') }}
        <input type="date" name="date_from" value="{{ $usage['period']['date_from'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
    </label>
    <label class="grid gap-1 text-sm text-slate-300">{{ __('To') }}
        <input type="date" name="date_to" value="{{ $usage['period']['date_to'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
    </label>
    <button class="self-end rounded-xl bg-indigo-500 px-5 py-2.5 font-semibold">{{ __('Apply') }}</button>
</form>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('AI responses') }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($usage['summary']['responses']) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($usage['summary']['conversations']) }} {{ __('conversations') }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Provider success') }}</p><p class="mt-2 text-3xl font-bold">{{ $usage['summary']['provider_success_rate'] === null ? '—' : number_format($usage['summary']['provider_success_rate'], 1).'%' }}</p><p class="mt-1 text-xs text-slate-500">{{ $usage['summary']['connected'] }} {{ __('connected') }} · {{ $usage['summary']['fallback'] }} {{ __('fallback') }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Total tokens') }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($usage['summary']['total_tokens']) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($usage['summary']['prompt_tokens']) }} {{ __('input') }} · {{ number_format($usage['summary']['completion_tokens']) }} {{ __('output') }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Tool calls') }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($usage['summary']['tool_calls']) }}</p><p class="mt-1 text-xs text-slate-500">{{ count($usage['tools']) }} {{ __('different tools') }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Average latency') }}</p><p class="mt-2 text-3xl font-bold">{{ $usage['summary']['average_latency_ms'] === null ? '—' : number_format($usage['summary']['average_latency_ms']).'ms' }}</p><p class="mt-1 text-xs text-slate-500">{{ __('Provider round-trip') }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Estimated cost') }}</p>
        @if($usage['summary']['cost_configured'])
            <p class="mt-2 text-2xl font-bold">USD {{ number_format($usage['summary']['estimated_cost_usd'], 6) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Based on configured token rates') }}</p>
        @else
            <p class="mt-2 text-xl font-bold text-slate-500">{{ __('Not configured') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Add token rates in environment settings to estimate cost.') }}</p>
        @endif
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('Provider & model usage') }}</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-slate-500"><tr><th class="pb-2 pr-4">{{ __('Provider') }}</th><th class="pb-2 pr-4">{{ __('Model') }}</th><th class="pb-2 pr-4 text-right">{{ __('Responses') }}</th><th class="pb-2 pr-4 text-right">{{ __('Tokens') }}</th><th class="pb-2 pr-4 text-right">{{ __('Tools') }}</th><th class="pb-2 pr-4 text-right">{{ __('Latency') }}</th><th class="pb-2 text-right">{{ __('Cost') }}</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($usage['providers'] as $row)
                    <tr>
                        <td class="py-3 pr-4 font-semibold">{{ str($row['provider'])->upper() }}</td>
                        <td class="py-3 pr-4 text-slate-400">{{ $row['model'] ?: '—' }}</td>
                        <td class="py-3 pr-4 text-right">{{ number_format($row['responses']) }}</td>
                        <td class="py-3 pr-4 text-right">{{ number_format($row['prompt_tokens'] + $row['completion_tokens']) }}</td>
                        <td class="py-3 pr-4 text-right">{{ number_format($row['tool_calls']) }}</td>
                        <td class="py-3 pr-4 text-right">{{ $row['average_latency_ms'] === null ? '—' : number_format($row['average_latency_ms']).'ms' }}</td>
                        <td class="py-3 text-right">{{ $usage['summary']['cost_configured'] ? 'USD '.number_format($row['estimated_cost_usd'], 6) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">{{ __('No AI usage in this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('Provider health') }}</h2>
        <div class="mt-4 grid grid-cols-3 gap-2">
            <div class="rounded-xl bg-emerald-500/10 p-3 text-center"><p class="text-[10px] uppercase text-emerald-300">{{ __('Connected') }}</p><p class="mt-1 text-2xl font-bold">{{ $usage['summary']['connected'] }}</p></div>
            <div class="rounded-xl bg-amber-500/10 p-3 text-center"><p class="text-[10px] uppercase text-amber-300">{{ __('Fallback') }}</p><p class="mt-1 text-2xl font-bold">{{ $usage['summary']['fallback'] }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3 text-center"><p class="text-[10px] uppercase text-slate-500">{{ __('Local') }}</p><p class="mt-1 text-2xl font-bold">{{ $usage['summary']['local'] }}</p></div>
        </div>
        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Fallback reasons') }}</p>
        <div class="mt-2 space-y-2">
            @forelse($usage['fallback_reasons'] as $row)
                <div class="flex items-center justify-between rounded-xl bg-slate-950 px-3 py-2.5"><span class="truncate text-sm text-slate-300">{{ $row['reason'] }}</span><span class="rounded-full bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-300">{{ $row['count'] }}</span></div>
            @empty
                <p class="rounded-xl bg-slate-950 px-3 py-4 text-sm text-slate-500">{{ __('No provider fallbacks recorded.') }}</p>
            @endforelse
        </div>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('FieldPulse tools used') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('How often the AI requested each read-only business tool.') }}</p>
        <div class="mt-4 space-y-2">
            @forelse($usage['tools']->take(12) as $row)
                <div class="flex items-center justify-between rounded-xl bg-slate-950 px-3 py-2.5"><code class="truncate text-xs text-indigo-300">{{ $row['tool'] }}</code><span class="rounded-full bg-white/5 px-2 py-1 text-xs font-semibold">{{ $row['count'] }}</span></div>
            @empty
                <p class="rounded-xl bg-slate-950 px-3 py-4 text-sm text-slate-500">{{ __('No tool calls recorded.') }}</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('Daily usage') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('AI responses and token usage by day in the tenant timezone.') }}</p>
        <div class="mt-4 max-h-80 overflow-y-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 bg-slate-900 text-slate-500"><tr><th class="pb-2 pr-4">{{ __('Date') }}</th><th class="pb-2 pr-4 text-right">{{ __('Responses') }}</th><th class="pb-2 pr-4 text-right">{{ __('Tokens') }}</th><th class="pb-2 text-right">{{ __('Cost') }}</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($usage['daily']->reverse() as $row)
                    <tr><td class="py-3 pr-4">{{ $row['date'] }}</td><td class="py-3 pr-4 text-right">{{ $row['responses'] }}</td><td class="py-3 pr-4 text-right">{{ number_format($row['tokens']) }}</td><td class="py-3 text-right">{{ $usage['summary']['cost_configured'] ? 'USD '.number_format($row['estimated_cost_usd'], 6) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">{{ __('No daily usage recorded.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-white/10 bg-slate-900 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Recent AI audit') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $usage['can_view_prompts'] ? __('Question-level audit is visible because you have audit access.') : __('Question text and user identity are hidden unless you have audit access.') }}</p>
        </div>
        <span class="rounded-full border border-white/10 bg-slate-950 px-3 py-1.5 text-xs text-slate-500">{{ $usage['period']['timezone'] }}</span>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead class="text-slate-500"><tr><th class="pb-2 pr-4">{{ __('Time') }}</th><th class="pb-2 pr-4">{{ __('User / question') }}</th><th class="pb-2 pr-4">{{ __('Provider') }}</th><th class="pb-2 pr-4">{{ __('Status') }}</th><th class="pb-2 pr-4">{{ __('Tools') }}</th><th class="pb-2 pr-4 text-right">{{ __('Tokens') }}</th><th class="pb-2 pr-4 text-right">{{ __('Latency') }}</th><th class="pb-2 text-right">{{ __('Cost') }}</th></tr></thead>
            <tbody class="divide-y divide-white/10">
            @forelse($usage['recent'] as $row)
                @php($providerStatus = $row['provider_status'] ?: 'local')
                <tr class="align-top">
                    <td class="py-3 pr-4 whitespace-nowrap">{{ $row['created_at']?->copy()->setTimezone($usage['period']['timezone'])->format('Y-m-d H:i') }}</td>
                    <td class="max-w-md py-3 pr-4">
                        @if($usage['can_view_prompts'])
                            <p class="font-semibold text-slate-200">{{ $row['user'] ?: '—' }}</p>
                            <p class="mt-1 text-slate-500">{{ str($row['question'] ?? '')->limit(180) }}</p>
                        @else
                            <span class="text-slate-600">{{ __('Restricted') }}</span>
                        @endif
                    </td>
                    <td class="py-3 pr-4">
                        <p class="font-semibold">{{ $row['provider'] ? str($row['provider'])->upper() : __('Local') }}</p>
                        <p class="mt-1 max-w-48 truncate text-slate-500">{{ $row['model'] ?: '—' }}</p>
                        @if($usage['can_view_prompts'] && $row['provider_request_id'])<p class="mt-1 max-w-48 truncate font-mono text-[10px] text-slate-600">{{ $row['provider_request_id'] }}</p>@endif
                    </td>
                    <td class="py-3 pr-4">
                        <span class="rounded-full px-2 py-1 font-semibold {{ $providerStatus === 'connected' ? 'bg-emerald-500/10 text-emerald-300' : ($providerStatus === 'fallback' ? 'bg-amber-500/10 text-amber-300' : 'bg-white/5 text-slate-400') }}">{{ __(str($providerStatus)->title()->toString()) }}</span>
                        @if($row['provider_http_status'])<p class="mt-1 text-[10px] text-slate-600">HTTP {{ $row['provider_http_status'] }}</p>@endif
                        @if($row['fallback_reason'])<p class="mt-1 text-[10px] text-amber-300">{{ $row['fallback_reason'] }}</p>@endif
                    </td>
                    <td class="max-w-xs py-3 pr-4">@if(is_array($row['tools_used']) && count($row['tools_used']))<div class="flex flex-wrap gap-1">@foreach($row['tools_used'] as $tool)<code class="rounded bg-indigo-500/10 px-1.5 py-0.5 text-[10px] text-indigo-300">{{ $tool }}</code>@endforeach</div>@else<span class="text-slate-600">—</span>@endif</td>
                    <td class="py-3 pr-4 text-right">{{ number_format((int) $row['prompt_tokens'] + (int) $row['completion_tokens']) }}</td>
                    <td class="py-3 pr-4 text-right">{{ $row['latency_ms'] === null ? '—' : number_format($row['latency_ms']).'ms' }}</td>
                    <td class="py-3 text-right">{{ $usage['summary']['cost_configured'] && $row['estimated_cost_usd'] !== null ? 'USD '.number_format((float) $row['estimated_cost_usd'], 6) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-500">{{ __('No AI audit records in this period.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-4 rounded-xl border border-white/10 bg-slate-900/60 px-4 py-3 text-xs leading-5 text-slate-500">{{ __('AI audit records never store API keys or provider bearer tokens. Cost is an estimate only when input/output token rates are configured.') }}</div>
</x-layouts.app>
