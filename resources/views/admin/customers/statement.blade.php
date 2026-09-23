<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa', 'ps'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Customer statement') }} · {{ $customer->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 28px auto; padding: 0 20px; color: #111827; background: #fff; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; margin-bottom: 24px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; }
        .toolbar label { display: grid; gap: 4px; font-size: 12px; color: #4b5563; }
        input, select, button, a.button { border: 1px solid #d1d5db; border-radius: 8px; padding: 9px 11px; background: white; color: #111827; text-decoration: none; }
        button { cursor: pointer; }
        .header { display: flex; justify-content: space-between; gap: 30px; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 18px; }
        .company { font-size: 24px; font-weight: 700; }
        .title { font-size: 28px; font-weight: 800; text-align: end; }
        .muted { color: #6b7280; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 22px 0; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
        .card strong { display: block; margin-top: 7px; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: start; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .num { text-align: end; white-space: nowrap; }
        .opening td { font-weight: 700; background: #f9fafb; }
        .footer { margin-top: 36px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 12px; color: #6b7280; }
        @media (max-width: 700px) { .header { display: block; } .title { text-align: start; margin-top: 16px; } .summary { grid-template-columns: 1fr 1fr; } }
        @media print { body { margin: 0 auto; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <form method="GET" class="toolbar">
        <label>{{ __('From') }}<input type="date" name="from" value="{{ $fromDate }}"></label>
        <label>{{ __('To') }}<input type="date" name="to" value="{{ $toDate }}"></label>
        <label>{{ __('Currency') }}
            <select name="currency">
                @foreach(['AFN', 'USD', 'PKR'] as $currency)
                    <option value="{{ $currency }}" @selected($statement['currency'] === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">{{ __('Apply') }}</button>
        <button type="button" onclick="window.print()">{{ __('Print / Save PDF') }}</button>
        <a class="button" href="{{ route('admin.customers.show', $customer) }}">{{ __('Back') }}</a>
    </form>

    <header class="header">
        <div>
            <div class="company">{{ $tenant?->name ?? config('app.name') }}</div>
            <div class="muted">{{ __('Customer statement of account') }}</div>
        </div>
        <div>
            <div class="title">{{ $customer->name }}</div>
            <div class="muted">{{ $customer->code }} · {{ $fromDate }} — {{ $toDate }}</div>
        </div>
    </header>

    <section class="summary">
        <div class="card"><span class="muted">{{ __('Opening') }}</span><strong>{{ number_format($statement['opening_balance'], 2) }} {{ $statement['currency'] }}</strong></div>
        <div class="card"><span class="muted">{{ __('Credit sales') }}</span><strong>{{ number_format($statement['debits'], 2) }} {{ $statement['currency'] }}</strong></div>
        <div class="card"><span class="muted">{{ __('Payments') }}</span><strong>{{ number_format($statement['credits'], 2) }} {{ $statement['currency'] }}</strong></div>
        <div class="card"><span class="muted">{{ __('Closing') }}</span><strong>{{ number_format($statement['closing_balance'], 2) }} {{ $statement['currency'] }}</strong></div>
    </section>

    <table>
        <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
        </thead>
        <tbody>
        <tr class="opening">
            <td>{{ $fromDate }}</td>
            <td>—</td>
            <td>{{ __('Opening balance') }}</td>
            <td></td>
            <td></td>
            <td class="num">{{ number_format($statement['opening_balance'], 2) }}</td>
        </tr>
        @forelse($statement['entries'] as $entry)
            <tr>
                <td>{{ $entry['occurred_at']->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</td>
                <td>{{ $entry['reference'] }}</td>
                <td>{{ __($entry['description']) }}</td>
                <td class="num">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '' }}</td>
                <td class="num">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '' }}</td>
                <td class="num">{{ number_format($entry['balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:24px">{{ __('No statement activity in this period.') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">{{ __('Only approved credit sales and verified collections are included.') }}</div>
</body>
</html>
