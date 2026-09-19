<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 8px; }
        h1 { font-size: 12px; margin: 0 0 4px; text-align: center; }
        .muted { color: #555; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th, td { padding: 2px 3px; text-align: left; }
        th { border-bottom: 1px solid #111; font-size: 8px; text-transform: uppercase; }
        .amount { text-align: right; }
        .total { border-top: 1px dashed #111; font-weight: bold; font-size: 11px; }
        .sep { border-top: 1px dashed #999; margin: 6px 0; }
    </style>
</head>
<body>
    {{-- #7813 — ticket de caisse POS (rouleau 80 mm), montants en minor units. --}}
    <h1>{{ $company?->name ?? '' }}</h1>
    <div class="center muted">
        {{ trim(implode(' — ', array_filter([$company?->city, $company?->country]))) }}
    </div>

    <div class="sep"></div>

    <div>{{ __('pdf.retail_receipt_title') }} — {{ $order->reference }}</div>
    <div class="muted">{{ __('pdf.date') }} : {{ $order->created_at?->format('Y-m-d H:i') }}</div>
    @if($order->customer_name !== null)
        <div class="muted">{{ __('pdf.retail_customer') }} : {{ $order->customer_name }}</div>
    @endif

    <table>
        <thead>
        <tr>
            <th>{{ __('pdf.description') }}</th>
            <th class="amount">{{ __('pdf.quantity') }}</th>
            <th class="amount">{{ __('pdf.total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="amount">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                <td class="amount">{{ number_format($item->line_total_minor / 100, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td>{{ __('pdf.subtotal') }}</td>
            <td class="amount">{{ number_format($order->subtotal_minor / 100, 2) }} {{ $order->currency }}</td>
        </tr>
        @if($order->discount_minor > 0)
        <tr>
            <td>{{ __('pdf.retail_discount') }}</td>
            <td class="amount">-{{ number_format($order->discount_minor / 100, 2) }} {{ $order->currency }}</td>
        </tr>
        @endif
        <tr class="total">
            <td>{{ __('pdf.grand_total') }}</td>
            <td class="amount">{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</td>
        </tr>
    </table>

    @if($payments->isNotEmpty())
        <div>{{ __('pdf.retail_payments_section') }}</div>
        <table>
            @foreach($payments as $payment)
            <tr>
                <td class="muted">{{ $payment->method->value }}</td>
                <td class="amount">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
            @endforeach
        </table>
    @endif

    <div class="sep"></div>
    <div class="center">{{ __('pdf.retail_thanks') }}</div>
    <div class="center muted">{{ __('pdf.generated_on', ['date' => now()->format('Y-m-d H:i')]) }}</div>
</body>
</html>
