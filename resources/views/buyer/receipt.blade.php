<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->tx_ref }} — {{ config('ticketing.brand.name') }}</title>
    <style>
        body{font:15px/1.5 Arial,sans-serif;color:#172033;max-width:760px;margin:40px auto;padding:0 24px}header{display:flex;justify-content:space-between;border-bottom:2px solid #172033;padding-bottom:16px}h1{margin:0}table{width:100%;border-collapse:collapse;margin:28px 0}th,td{text-align:left;padding:10px;border-bottom:1px solid #d8dee9}.amount{text-align:right}.total{font-size:20px;font-weight:bold}.muted{color:#64748b}.print{padding:10px 16px;border:0;border-radius:6px;background:#0e7490;color:#fff;cursor:pointer}@media print{.print{display:none}body{margin:0}}
    </style>
</head>
<body>
    <header>
        <div><h1>{{ config('ticketing.brand.name') }}</h1><div class="muted">{{ config('ticketing.brand.company') }}</div></div>
        <div><strong>PAYMENT RECEIPT</strong><br>{{ $order->paid_at?->format('d M Y H:i') }}</div>
    </header>
    <p><strong>Receipt / order reference:</strong> {{ $order->tx_ref }}<br><strong>Customer:</strong> {{ $order->buyer_name }} ({{ $order->buyer_email }})<br><strong>Event / service:</strong> {{ $order->listing->title }}<br><strong>Payment method:</strong> {{ strtoupper((string) $order->momo_provider) }} Mobile Money<br><strong>Gateway reference:</strong> {{ $order->gateway_reference ?: '—' }}</p>
    <table>
        <thead><tr><th>Description</th><th>Qty</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            @foreach($order->items as $item)
                <tr><td>{{ $item->label() }}</td><td>{{ $item->quantity }}</td><td class="amount">{{ \App\Support\Money::format($item->line_total_minor, $order->currency) }}</td></tr>
            @endforeach
            <tr><td colspan="2">Subtotal</td><td class="amount">{{ \App\Support\Money::format($order->subtotal_minor, $order->currency) }}</td></tr>
            <tr><td colspan="2">Platform fee ({{ \App\Support\Money::percent($order->commission_bps_applied) }})</td><td class="amount">{{ \App\Support\Money::format($order->commission_minor, $order->currency) }}</td></tr>
            <tr class="total"><td colspan="2">Total paid</td><td class="amount">{{ \App\Support\Money::format($order->total_minor, $order->currency) }}</td></tr>
        </tbody>
    </table>
    <p class="muted">Payment status: {{ strtoupper($order->status) }} · Paid at {{ $order->paid_at?->format('d M Y H:i T') }}. Keep this receipt for your records.</p>
    <button class="print" onclick="window.print()">Print / Save as PDF</button>
</body>
</html>
