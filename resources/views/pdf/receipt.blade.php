<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width={{ $settings['paper_width_mm'] }}mm">
    <title>Receipt {{ $receipt['receipt_no'] ?? $sale_no }}</title>
    <style>
        @page { size: {{ $settings['paper_width_mm'] }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            width: {{ $settings['paper_width_mm'] }}mm;
            min-width: {{ $settings['paper_width_mm'] }}mm;
            margin: 0 auto;
            padding: 3mm;
            font-family: 'Courier New', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            line-height: 1.45;
            color: #000;
            background: #fff;
        }
        .receipt { width: 100%; }
        .center { text-align: center; }
        .brand { font-weight: 700; font-size: 13px; text-transform: uppercase; }
        .muted { opacity: 0.75; }
        .row { display: flex; justify-content: space-between; gap: 4mm; }
        .item-name { overflow-wrap: anywhere; }
        .line { border: 0; border-top: 1px dashed #000; margin: 3mm 0; }
        .total { font-size: 13px; font-weight: 700; }
        .amount { white-space: nowrap; text-align: right; }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 1px 0; vertical-align: top; }
        .footer { text-align: center; font-size: 10px; }
    </style>
</head>
<body onload="window.print()" onafterprint="if (window.opener && window.opener !== window) { window.close(); }">
    <div class="receipt">
        <div class="center">
            <div class="brand">{{ $receipt['branch']['name'] ?? '' }}</div>
            @if(! empty($receipt['branch']['address']))
                <div>{{ $receipt['branch']['address'] }}</div>
            @endif
            @if(! empty($receipt['branch']['phone']))
                <div>{{ $receipt['branch']['phone'] }}</div>
            @endif
            @foreach ($settings['header_lines'] as $headerLine)
                <div class="muted">{{ $headerLine }}</div>
            @endforeach
        </div>

        <hr class="line">

        <div class="center muted">
            <div>NO: {{ $receipt['receipt_no'] ?? '' }}</div>
            @if(! empty($receipt['cashier']['name']))
                <div>Cashier: {{ $receipt['cashier']['name'] }}</div>
            @endif
            @if(! empty($receipt['shift_no']))
                <div>Shift: {{ $receipt['shift_no'] }}</div>
            @endif
            <div>{{ \Illuminate\Support\Carbon::parse($receipt['sold_at'])->format('Y-m-d H:i') }}</div>
        </div>

        @if(! empty($receipt['customer']['name']))
            <div class="muted">
                <div>{{ $receipt['customer']['name'] }}</div>
                @if(! empty($receipt['customer']['phone']))
                    <div>{{ $receipt['customer']['phone'] }}</div>
                @endif
            </div>
            <hr class="line">
        @endif

        @foreach ($receipt['items'] as $item)
            <div class="item-name">{{ $item['name'] ?? 'Item' }} &#64; x{{ (float) $item['quantity'] }}</div>
            <div class="row muted">
                @if(! empty($item['variant']))
                    <span>{{ $item['variant'] }}</span>
                @else
                    <span>&nbsp;</span>
                @endif
                <span class="amount">{{ number_format((float) $item['line_total'], 2) }}</span>
            </div>
            @if((float) $item['original_unit_price'] * (float) $item['quantity'] - (float) $item['line_total'] > 0)
                <div class="row muted">
                    <span>&nbsp;</span>
                    <span class="amount">-{{ number_format((float) $item['original_unit_price'] * (float) $item['quantity'] - (float) $item['line_total'], 2) }}</span>
                </div>
            @endif
        @endforeach

        @if(count($receipt['coupons']) > 0)
            <hr class="line">
            @foreach ($receipt['coupons'] as $coupon)
                <div class="row muted">
                    <span>{{ $coupon['code'] }}</span>
                    <span class="amount">-{{ number_format((float) $coupon['discount_amount'], 2) }}</span>
                </div>
            @endforeach
        @endif

        <hr class="line">

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="amount">{{ number_format((float) $receipt['subtotal'], 2) }}</td>
            </tr>
            <tr>
                <td>Discounts</td>
                <td class="amount">-{{ number_format((float) $receipt['discount_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="amount">{{ number_format((float) $receipt['tax_total'], 2) }}</td>
            </tr>
            <tr class="total">
                <td>TOTAL ({{ $currency }})</td>
                <td class="amount">{{ number_format((float) $receipt['total'], 2) }}</td>
            </tr>
        </table>

        @if(! empty($receipt['has_price_reduction']) && (float) $receipt['price_reduction_amount'] > 0)
            <hr class="line">
            <div class="row">
                <span>Discount</span>
                <span class="amount">-{{ number_format((float) $receipt['price_reduction_amount'], 2) }}</span>
            </div>
            @php
                $reductionLabels = [
                    'discount' => 'Discount given to customer',
                    'loss' => 'Sold at a loss',
                    'overpayment' => 'Overpayment (change given)',
                ];
            @endphp
            <div class="muted">{{ $reductionLabels[$receipt['price_reduction_reason'] ?? 'discount'] ?? 'Discount given to customer' }}</div>
        @endif

        <hr class="line">

        @foreach ($receipt['payments'] as $payment)
            <div class="row">
                <span>{{ $payment['method'] }}@if(! empty($payment['reference'])) ({{ $payment['reference'] }})@endif</span>
                <span class="amount">{{ number_format((float) $payment['amount'], 2) }}</span>
            </div>
        @endforeach

        <div class="row muted">
            <span>Amount paid</span>
            <span class="amount">{{ number_format((float) $receipt['amount_paid'], 2) }}</span>
        </div>
        <div class="row muted">
            <span>Change</span>
            <span class="amount">{{ number_format((float) $receipt['change_amount'], 2) }}</span>
        </div>

        <hr class="line">

        <div class="footer">{{ $settings['footer_message'] }}</div>
    </div>
</body>
</html>