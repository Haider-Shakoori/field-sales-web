<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa', 'ps'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 28px auto; padding: 0 20px; color: #111827; background: #fff; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 20px; }
        .toolbar button { border: 0; border-radius: 8px; padding: 10px 16px; cursor: pointer; }
        .header { display: flex; justify-content: space-between; gap: 30px; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 18px; }
        .company { font-size: 24px; font-weight: 700; }
        .invoice-title { font-size: 30px; font-weight: 800; text-align: end; }
        .muted { color: #6b7280; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin: 24px 0; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }
        .row { display: flex; justify-content: space-between; gap: 18px; padding: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 11px 8px; border-bottom: 1px solid #e5e7eb; text-align: start; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .num { text-align: end; white-space: nowrap; }
        .totals { margin: 18px 0 0 auto; max-width: 360px; }
        .total { font-size: 18px; font-weight: 800; border-top: 2px solid #111827; margin-top: 6px; padding-top: 9px; }
        .footer { margin-top: 40px; padding-top: 14px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; }
        @media (max-width: 640px) { .header, .grid { display: block; } .invoice-title { text-align: start; margin-top: 18px; } .card { margin-top: 12px; } }
        @media print { body { margin: 0 auto; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">{{ __('Print / Save PDF') }}</button></div>

    <header class="header">
        <div>
            <div class="company">{{ $tenant?->name ?? config('app.name') }}</div>
            <div class="muted">{{ __('Sales invoice') }}</div>
        </div>
        <div>
            <div class="invoice-title">{{ __('INVOICE') }}</div>
            <div class="muted">{{ $order->order_number }}</div>
        </div>
    </header>

    <section class="grid">
        <div class="card">
            <strong>{{ __('Bill to') }}</strong>
            <div style="margin-top:10px;font-size:18px;font-weight:700">{{ $order->customer?->name }}</div>
            <div class="muted">{{ $order->customer?->code }}</div>
            @if($order->customer?->phone)<div>{{ $order->customer->phone }}</div>@endif
            @if($order->customer?->address)<div>{{ $order->customer->address }}</div>@endif
        </div>
        <div class="card">
            <div class="row"><span class="muted">{{ __('Date') }}</span><strong>{{ $order->ordered_at?->format('Y-m-d H:i') }}</strong></div>
            <div class="row"><span class="muted">{{ __('Payment') }}</span><strong>{{ __(str($order->payment_type)->title()->toString()) }}</strong></div>
            @if($order->payment_type === 'credit')
                <div class="row"><span class="muted">{{ __('Due date') }}</span><strong>{{ $order->due_date?->format('Y-m-d') ?? '—' }}</strong></div>
            @endif
            <div class="row"><span class="muted">{{ __('Salesman') }}</span><strong>{{ $order->salesman?->full_name ?? $order->salesman?->user?->name ?? '—' }}</strong></div>
            <div class="row"><span class="muted">{{ __('Currency') }}</span><strong>{{ $order->currency }}</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>{{ __('Product') }}</th>
            <th class="num">{{ __('Qty') }}</th>
            <th class="num">{{ __('Unit price') }}</th>
            <th class="num">{{ __('Discount') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td><strong>{{ $item->product_name }}</strong><br><span class="muted">{{ $item->product_sku }} · {{ $item->unit }}</span></td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">{{ number_format((float) $item->discount_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>{{ __('Subtotal') }}</span><span>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</span></div>
        <div class="row"><span>{{ __('Discount') }}</span><span>{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency }}</span></div>
        <div class="row total"><span>{{ __('Total') }}</span><span>{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</span></div>
    </div>

    @if($order->notes)
        <div class="card" style="margin-top:24px"><strong>{{ __('Notes') }}</strong><div style="margin-top:8px">{{ $order->notes }}</div></div>
    @endif

    <div class="footer">{{ __('Generated from an approved FieldPulse order.') }}</div>
</body>
</html>
