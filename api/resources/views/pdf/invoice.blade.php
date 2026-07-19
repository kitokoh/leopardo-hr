<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 30px; }
        .header { margin-bottom: 20px; }
        .company-name { font-size: 16px; font-weight: bold; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 25px 0 5px; }
        .invoice-number { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; color: #555; }
        .amount { text-align: right; }
        .total-section { margin-top: 15px; }
        .total-row { font-weight: bold; border-top: 2px solid #333; }
        .total-ttc { font-size: 16px; color: #2563eb; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .info-label { font-size: 9px; color: #888; text-transform: uppercase; }
        .info-value { font-size: 11px; margin-bottom: 6px; }
        .footer { margin-top: 30px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="company-name">{{ __('pdf.invoice_platform_name') }}</div>
    <div style="font-size: 10px; color: #666;">{{ __('pdf.invoice_platform_tagline') }}</div>

    <div class="title">{{ __('pdf.invoice_document_title') }}</div>
    <div class="invoice-number">{{ $invoice->invoice_number ?? 'LEO-'.now()->format('Y').'-'.str_pad((string)($invoice->id ?? 0), 4, '0', STR_PAD_LEFT) }}</div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">{{ __('pdf.invoice_billed_to') }}</div>
            <div class="info-value">{{ $company->name ?? __('pdf.invoice_client_fallback') }}</div>
            <div class="info-value" style="font-size: 10px; color: #666;">{{ $company->address ?? '' }}</div>
            <div class="info-value" style="font-size: 10px; color: #666;">{{ $company->city ?? '' }} {{ $company->country ?? '' }}</div>
            @if(!empty($company->tax_id))
            <div class="info-label">{{ __('pdf.invoice_tax_id') }}</div>
            <div class="info-value">{{ $company->tax_id }}</div>
            @endif
        </div>
        <div class="info-col" style="text-align: right;">
            <div class="info-label">{{ __('pdf.invoice_issue_date') }}</div>
            <div class="info-value">{{ $invoice->created_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
            <div class="info-label">{{ __('pdf.invoice_due_date') }}</div>
            <div class="info-value">{{ $invoice->due_date?->format('d/m/Y') ?? now()->addDays(30)->format('d/m/Y') }}</div>
            <div class="info-label">{{ __('pdf.invoice_status') }}</div>
            <div class="info-value">
                <span class="status-badge {{ $invoice->status === 'paid' ? 'status-paid' : ($invoice->status === 'overdue' ? 'status-overdue' : 'status-pending') }}">
                    {{ strtoupper($invoice->status ?? 'pending') }}
                </span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('pdf.invoice_column_description') }}</th>
                <th class="amount">{{ __('pdf.invoice_column_quantity') }}</th>
                <th class="amount">{{ __('pdf.invoice_column_unit_price') }}</th>
                <th class="amount">{{ __('pdf.invoice_column_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('pdf.invoice_subscription_line', ['plan' => $invoice->plan_name ?? 'Leopardo RH', 'period' => $invoice->period ?? __('pdf.invoice_period_monthly')]) }}</td>
                <td class="amount">1</td>
                <td class="amount">{{ number_format($invoice->amount_ht ?? $invoice->amount ?? 0, 2, ',', ' ') }} {{ $invoice->currency ?? 'EUR' }}</td>
                <td class="amount">{{ number_format($invoice->amount_ht ?? $invoice->amount ?? 0, 2, ',', ' ') }} {{ $invoice->currency ?? 'EUR' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <table>
            <tr>
                <td>{{ __('pdf.invoice_subtotal_before_tax') }}</td>
                <td class="amount">{{ number_format($invoice->amount_ht ?? $invoice->amount ?? 0, 2, ',', ' ') }} {{ $invoice->currency ?? 'EUR' }}</td>
            </tr>
            <tr>
                <td>{{ __('pdf.invoice_tax_line', ['rate' => $invoice->tax_rate ?? 0]) }}</td>
                <td class="amount">{{ number_format($invoice->tax_amount ?? 0, 2, ',', ' ') }} {{ $invoice->currency ?? 'EUR' }}</td>
            </tr>
            <tr class="total-row">
                <td>{{ __('pdf.invoice_total_with_tax') }}</td>
                <td class="amount total-ttc">{{ number_format($invoice->total ?? $invoice->amount ?? 0, 2, ',', ' ') }} {{ $invoice->currency ?? 'EUR' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div>{{ __('pdf.invoice_generated_footer', ['date' => now()->format('d/m/Y H:i')]) }}</div>
        @if(!empty($legalMentions))
        <div style="margin-top: 6px;">{{ $legalMentions }}</div>
        @endif
    </div>
</body>
</html>
