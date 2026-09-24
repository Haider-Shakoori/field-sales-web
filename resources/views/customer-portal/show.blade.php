<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa', 'ps'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ __('Customer portal') }} · {{ $customer->name }}</title>
    @php
        $cssPath = public_path('css/app.css');
        $cssVersion = is_file($cssPath) ? filemtime($cssPath) : '1';
    @endphp
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="mb-3 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-sky-400 text-lg font-black text-white">F</span>
                <div>
                    <p class="font-bold">{{ $tenant?->name ?? config('app.name') }}</p>
                    <p class="text-sm text-slate-400">{{ __('Customer portal') }}</p>
                </div>
            </div>
            <h1 class="text-3xl font-bold">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $customer->code }} · {{ $customer->phone ?? __('No phone') }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-right text-sm">
            <p class="text-slate-400">{{ __('Access expires') }}</p>
            <p class="font-semibold">{{ $access->expires_at?->setTimezone($timezone)->format('Y-m-d H:i') }}</p>
        </div>
    </header>

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 sm:grid-cols-4">
        <label class="grid gap-1 text-sm text-slate-300">{{ __('From') }}<input type="date" name="from" value="{{ $fromDate }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2"></label>
        <label class="grid gap-1 text-sm text-slate-300">{{ __('To') }}<input type="date" name="to" value="{{ $toDate }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2"></label>
        <label class="grid gap-1 text-sm text-slate-300">{{ __('Currency') }}
            <select name="currency" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
                @foreach(['AFN', 'USD', 'PKR'] as $option)
                    <option value="{{ $option }}" @selected($currency === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <button class="self-end rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">{{ __('Apply') }}</button>
    </form>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Opening') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($statement['opening_balance'], 2) }} {{ $statement['currency'] }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Credit sales') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($statement['debits'], 2) }} {{ $statement['currency'] }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Payments') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($statement['credits'], 2) }} {{ $statement['currency'] }}</p></div>
        <div class="rounded-2xl border border-indigo-400/30 bg-indigo-500/10 p-5"><p class="text-xs uppercase tracking-wide text-indigo-200">{{ __('Closing balance') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($statement['closing_balance'], 2) }} {{ $statement['currency'] }}</p></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold">{{ __('Approved invoices') }}</h2><span class="text-xs text-slate-500">{{ __('Latest 25') }}</span></div>
            <div class="space-y-3">
                @forelse($orders as $order)
                    <div class="rounded-xl bg-slate-950 p-4">
                        <div class="flex items-center justify-between gap-3"><span class="font-semibold">{{ $order->order_number }}</span><span class="font-semibold">{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</span></div>
                        <div class="mt-1 text-xs text-slate-400">{{ $order->ordered_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }} · {{ __(str($order->payment_type)->title()->toString()) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('No approved invoices yet.') }}</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold">{{ __('Verified payments') }}</h2><span class="text-xs text-slate-500">{{ __('Latest 25') }}</span></div>
            <div class="space-y-3">
                @forelse($collections as $collection)
                    <div class="rounded-xl bg-slate-950 p-4">
                        <div class="flex items-center justify-between gap-3"><span class="font-semibold">{{ $collection->receipt_number }}</span><span class="font-semibold text-emerald-300">{{ number_format((float) $collection->amount, 2) }} {{ $collection->currency }}</span></div>
                        <div class="mt-1 text-xs text-slate-400">{{ $collection->collected_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }} · {{ __(str($collection->payment_method)->replace('_', ' ')->title()->toString()) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('No verified payments yet.') }}</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <h2 class="text-lg font-semibold">{{ __('Statement') }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-400"><tr><th class="pb-2 pr-4">{{ __('Date') }}</th><th class="pb-2 pr-4">{{ __('Reference') }}</th><th class="pb-2 pr-4">{{ __('Description') }}</th><th class="pb-2 pr-4 text-right">{{ __('Debit') }}</th><th class="pb-2 pr-4 text-right">{{ __('Credit') }}</th><th class="pb-2 text-right">{{ __('Balance') }}</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($statement['entries'] as $entry)
                            <tr><td class="py-3 pr-4 whitespace-nowrap">{{ $entry['occurred_at']->copy()->setTimezone($timezone)->format('Y-m-d') }}</td><td class="py-3 pr-4">{{ $entry['reference'] }}</td><td class="py-3 pr-4">{{ __($entry['description']) }}</td><td class="py-3 pr-4 text-right">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '' }}</td><td class="py-3 pr-4 text-right">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '' }}</td><td class="py-3 text-right font-semibold">{{ number_format($entry['balance'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">{{ __('No statement activity in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <footer class="mt-8 border-t border-white/10 pt-4 text-xs text-slate-500">
        {{ __('This portal is read-only. Only approved sales and verified payments are shown.') }}
    </footer>
</div>
</body>
</html>
