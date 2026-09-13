<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dispatch Note — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .brand { color: #4b5563; font-size: 10px; }
        .meta { font-size: 10px; color: #374151; margin: 12px 0 16px; }
        .meta td { padding: 2px 12px 2px 0; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        .foot { margin-top: 24px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <h1>Dispatch Note</h1>
    <div class="brand">{{ $order->dispatch_note_no ?: 'DN-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}</div>

    <table class="meta">
        <tr>
            <td><strong>From (Godown):</strong><br>{{ $order->godown?->name }}</td>
            <td><strong>To (Branch):</strong><br>{{ $order->branch?->name }}</td>
            <td><strong>Order:</strong><br>{{ $order->order_number }}</td>
        </tr>
        <tr>
            <td><strong>Priority:</strong><br>{{ ucfirst($order->priority) }}</td>
            <td><strong>Required by:</strong><br>{{ $order->required_date?->toDateString() }}</td>
            <td><strong>Dispatched:</strong><br>{{ $order->dispatched_at?->toDateTimeString() }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Requested</th>
                <th>Approved</th>
                <th>Picked</th>
                <th>Dispatched</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product?->name }}@if ($item->product?->sku) ({{ $item->product->sku }})@endif</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->qty_requested, 3), '0'), '.') }}</td>
                    <td>{{ $item->qty_approved === null ? '—' : rtrim(rtrim(number_format((float) $item->qty_approved, 3), '0'), '.') }}</td>
                    <td>{{ $item->qty_picked === null ? '—' : rtrim(rtrim(number_format((float) $item->qty_picked, 3), '0'), '.') }}</td>
                    <td>{{ $item->qty_dispatched === null ? '—' : rtrim(rtrim(number_format((float) $item->qty_dispatched, 3), '0'), '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No line items.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <div class="foot">
        Generated: {{ now()->format('Y-m-d H:i:s') }} &middot; Dispatcher:
        {{ $order->dispatcher?->name }}
        &middot; Carrier must verify quantities against this note on receipt.
    </div>
</body>
</html>