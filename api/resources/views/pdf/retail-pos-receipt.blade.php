<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    @php
        // BC-17 #7813 — ticket de caisse 80 mm (DomPDF). Image de marque du
        // tenant réutilisée telle quelle (#7713) ; montants en minor units
        // convertis à l'affichage (pattern /100 du POS, commerce-format.ts).
        $pdfBrandLogo = \App\Support\PdfBranding::logoPath($company ?? null);
        $money = static fn (int $minor): string => number_format($minor / 100, 2, '.', ' ');
    @endphp
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 8px; }
        .center { text-align: center; }
        .company-name { font-size: 12px; font-weight: bold; }
        .title { font-size: 11px; font-weight: bold; margin: 6px 0; }
        .muted { color: #555; }
        .sep { border-top: 1px dashed #999; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; text-align: left; }
        .amount { text-align: right; }
        .total { font-size: 11px; font-weight: bold; }
        .footer { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="center">
        @if($pdfBrandLogo !== null)
        <img src="{{ $pdfBrandLogo }}" alt="" style="height: 32px; margin-bottom: 4px;">
        @endif
        <div class="company-name">{{ $company->name ?? '' }}</div>
        @if(($company->city ?? null) || ($company->country ?? null))
        <div class="muted">{{ trim(($company->city ?? '').' '.($company->country ?? '')) }}</div>
        @endif
        <div class="title">{{ __('pdf.retail_receipt_title') }}</div>
    </div>

    <div>{{ __('pdf.retail_order_reference_label') }} : {{ $order->reference }}</div>
    <div>{{ __('pdf.retail_date_label') }} : {{ $order->created_at?->format('Y-m-d H:i') }}</div>
    <div>{{ __('pdf.retail_location_label') }} : {{ $order->location?->name ?? '#'.$order->location_id }}</div>
    <div>{{ __('pdf.retail_status_label') }} : {{ $order->status->value }}</div>

    <div class="sep"></div>

    <table>
        <thead>
        <tr>
            <th>{{ __('pdf.retail_column_product') }}</th>
            <th class="amount">{{ __('pdf.retail_column_quantity') }}</th>
            <th class="amount">{{ __('pdf.retail_column_unit_price') }}</th>
            <th class="amount">{{ __('pdf.retail_column_total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ $item->product_name }}</td>
            <td class="amount">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
            <td class="amount">{{ $money($item->unit_price_minor) }}</td>
            <td class="amount">{{ $money($item->line_total_minor) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>

    <div class="sep"></div>

    <table>
        <tr>
            <td>{{ __('pdf.retail_subtotal_label') }}</td>
            <td class="amount">{{ $money($order->subtotal_minor) }} {{ $order->currency }}</td>
        </tr>
        @if($order->discount_minor > 0)
        <tr>
            <td>{{ __('pdf.retail_discount_label') }}</td>
            <td class="amount">-{{ $money($order->discount_minor) }} {{ $order->currency }}</td>
        </tr>
        @endif
        <tr class="total">
            <td>{{ __('pdf.retail_total_label') }}</td>
            <td class="amount">{{ $money($order->total_minor) }} {{ $order->currency }}</td>
        </tr>
    </table>

    @if($payments->isNotEmpty())
    <div class="sep"></div>
    <div>{{ __('pdf.retail_payments_title') }}</div>
    <table>
        @foreach($payments as $payment)
        <tr>
            <td>{{ $payment->method->value }} {{ $payment->paid_at?->format('H:i') }}</td>
            <td class="amount">{{ $money($payment->amount_minor) }} {{ $payment->currency }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="sep"></div>
    <div class="center footer">{{ __('pdf.retail_receipt_footer') }}</div>
</body>
</html>
