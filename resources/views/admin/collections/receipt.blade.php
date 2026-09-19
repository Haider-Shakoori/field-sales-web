<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $collection->receipt_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 720px; margin: 32px auto; color: #111; }
        .row { display: flex; justify-content: space-between; gap: 24px; padding: 8px 0; border-bottom: 1px solid #ddd; }
        .muted { color: #666; }
        .amount { font-size: 28px; font-weight: 700; margin: 24px 0; }
        @media print { button { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print</button>
    <h1>Collection Receipt</h1>
    <p class="muted">{{ $collection->receipt_number }}</p>
    <div class="amount">{{ number_format((float) $collection->amount, 2) }} {{ $collection->currency }}</div>
    <div class="row"><span>Customer</span><strong>{{ $collection->customer?->name }}</strong></div>
    <div class="row"><span>Collected by</span><strong>{{ $collection->salesman?->full_name ?? $collection->salesman?->user?->name }}</strong></div>
    <div class="row"><span>Date</span><strong>{{ $collection->collected_at?->format('Y-m-d H:i:s') }}</strong></div>
    <div class="row"><span>Payment method</span><strong>{{ str($collection->payment_method)->replace('_', ' ')->title() }}</strong></div>
    <div class="row"><span>Reference</span><strong>{{ $collection->reference_number ?? '—' }}</strong></div>
    <div class="row"><span>Status</span><strong>{{ str($collection->status)->title() }}</strong></div>
    @if($collection->notes)
        <p><strong>Notes:</strong> {{ $collection->notes }}</p>
    @endif
</body>
</html>
