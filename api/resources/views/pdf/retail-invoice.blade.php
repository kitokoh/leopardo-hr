<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    @php
        // #7713 — image de marque du tenant (mêmes helpers que pdf.invoice) :
        // sans branding exploitable, le rendu reste identique.
        $pdfBrandLogo = \App\Support\PdfBranding::logoPath($company ?? null);
        $pdfBrandColor = \App\Support\PdfBranding::primaryColor($company ?? null, '');
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
        .total-row { font-weight: bold; border-top: 2px solid #333; }
        .muted { color: #666; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .footer { margin-top: 30px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
        @if($pdfBrandColor !== '') th { background: {{ $pdfBrandColor }}; color: #ffffff; } @endif
    </style>
</head>
<body>
    {{-- #7813 — facture Retail (POS + commandes web), numérotation légale par tenant. --}}
    @if($pdfBrandLogo !== null)
    <img src="{{ $pdfBrandLogo }}" alt="" style="height: 48px; margin-bottom: 8px;">
    @endif

    <div class="company-name">{{ $company?->name ?? '' }}</div>
    <div class="muted">{{ trim(implode(' — ', array_filter([$company?->city, $company?->country]))) }}</div>

    <div class="title">{{ __('pdf.invoice_title') }}</div>
    <div class="invoice-number">
        {{ $order->invoice_number }} — {{ $order->invoiced_at?->format('Y-m-d') }}
        &nbsp;
        @if($capturedMinor >= $order->total_minor)
            <span class="status-badge status-paid">{{ __('pdf.retail_status_paid') }}</span>
        @else
            <span class="status-badge status-pending">{{ __('pdf.retail_status_unpaid') }}</span>
        @endif
    </div>

    <table>
        <tr>
            <td class="muted">{{ __('pdf.retail_invoice_seller') }} : {{ $company?->name ?? '' }}</td>
            <td class="muted">{{ __('pdf.retail_customer') }} : {{ $order->customer_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('pdf.date') }} : {{ $order->created_at?->format('Y-m-d') }}</td>
            <td class="muted">{{ $order->reference }}</td>
        </tr>
    </table>

    <table>
        <thead>
        <tr>
            <th>{{ __('pdf.description') }}</th>
            <th class="amount">{{ __('pdf.quantity') }}</th>
            <th class="amount">{{ __('pdf.unit_price') }}</th>
            <th class="amount">{{ __('pdf.total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="amount">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                <td class="amount">{{ number_format($item->unit_price_minor / 100, 2) }}</td>
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
        <tr class="total-row">
            <td>{{ __('pdf.grand_total') }}</td>
            <td class="amount">{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</td>
        </tr>
    </table>

    @if($payments->isNotEmpty())
        <div>{{ __('pdf.retail_payments_section') }}</div>
        <table>
            @foreach($payments as $payment)
            <tr>
                <td class="muted">{{ $payment->method->value }} — {{ $payment->paid_at?->format('Y-m-d H:i') }}</td>
                <td class="amount">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">{{ __('pdf.generated_on', ['date' => now()->format('Y-m-d H:i')]) }}</div>
</body>
</html>
