<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    @php
        // BC-17 #7813 — facture de vente Retail (A4, DomPDF), numéro légal
        // attribué par RetailInvoiceService AVANT le rendu. Branding tenant
        // réutilisé (#7713) ; minor units converties à l'affichage.
        $pdfBrandLogo = \App\Support\PdfBranding::logoPath($company ?? null);
        $pdfBrandColor = \App\Support\PdfBranding::primaryColor($company ?? null, '');
        $money = static fn (int $minor): string => number_format($minor / 100, 2, '.', ' ');
    @endphp
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 30px; }
        .company-name { font-size: 16px; font-weight: bold; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 25px 0 5px; }
        .invoice-number { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; color: #555; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; border-top: 2px solid #333; font-size: 13px; }
        .meta { margin-bottom: 20px; }
        .meta div { margin-bottom: 4px; }
        .muted { color: #666; }
        .footer { margin-top: 30px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
        @if($pdfBrandColor !== '') th { background: {{ $pdfBrandColor }}; color: #ffffff; } @endif
    </style>
</head>
<body>
    @if($pdfBrandLogo !== null)
    <img src="{{ $pdfBrandLogo }}" alt="" style="height: 48px; margin-bottom: 8px;">
    @endif
    <div class="company-name">{{ $company->name ?? '' }}</div>
    @if(($company->city ?? null) || ($company->country ?? null))
    <div class="muted">{{ trim(($company->city ?? '').' '.($company->country ?? '')) }}</div>
    @endif

    <div class="title">{{ __('pdf.retail_invoice_title') }}</div>
    <div class="invoice-number">{{ __('pdf.retail_invoice_number_label') }} {{ $order->invoice_number }}</div>

    <div class="meta">
        <div>{{ __('pdf.retail_date_label') }} : {{ $order->invoiced_at?->format('Y-m-d') }}</div>
        <div>{{ __('pdf.retail_order_reference_label') }} : {{ $order->reference }}</div>
        <div>{{ __('pdf.retail_location_label') }} : {{ $order->location?->name ?? '#'.$order->location_id }}</div>
    </div>

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
        <tr class="total-row">
            <td>{{ __('pdf.retail_total_label') }}</td>
            <td class="amount">{{ $money($order->total_minor) }} {{ $order->currency }}</td>
        </tr>
    </table>

    @if($payments->isNotEmpty())
    <h4>{{ __('pdf.retail_payments_title') }}</h4>
    <table>
        @foreach($payments as $payment)
        <tr>
            <td>{{ $payment->method->value }}</td>
            <td>{{ $payment->paid_at?->format('Y-m-d H:i') }}</td>
            <td class="amount">{{ $money($payment->amount_minor) }} {{ $payment->currency }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="footer">
        {{ $company->name ?? '' }} — {{ $order->invoice_number }}
    </div>
</body>
</html>
