<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { border-bottom: 2px solid #10b981; padding-bottom: 12px; margin-bottom: 20px; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ __('pdf.payment_advance_title') }}</p>
        <p class="muted">{{ $company?->name ?? 'Leopardo RH' }} - {{ __('pdf.payment_document_reference', ['id' => $document->id]) }}</p>
    </div>

    <div class="box">
        <strong>{{ __('pdf.payment_document_collaborator') }}</strong><br>
        {{ trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')) ?: __('pdf.payment_document_not_specified') }}<br>
        <span class="muted">{{ $employee?->email }}</span>
    </div>

    <table>
        <tr><th>{{ __('pdf.payment_advance_amount') }}</th><td>{{ number_format((float) ($salaryAdvance?->amount ?? $metadata['amount'] ?? 0), 2, ',', ' ') }}</td></tr>
        <tr><th>{{ __('pdf.payment_advance_reason') }}</th><td>{{ $salaryAdvance?->reason ?? __('pdf.payment_document_not_specified') }}</td></tr>
        <tr><th>{{ __('pdf.payment_advance_payment_reference') }}</th><td>{{ $salaryAdvance?->payment_reference ?? ($metadata['payment_reference'] ?? __('pdf.payment_document_not_specified')) }}</td></tr>
        <tr><th>{{ __('pdf.payment_advance_declared_on') }}</th><td>{{ $salaryAdvance?->payment_declared_at?->format('Y-m-d H:i') ?? ($metadata['payment_declared_at'] ?? __('pdf.payment_document_not_specified')) }}</td></tr>
        <tr><th>{{ __('pdf.payment_advance_validation_status') }}</th><td>{{ $salaryAdvance?->validation_status ?? 'payment_declared' }}</td></tr>
    </table>

    <p class="muted">{{ __('pdf.payment_advance_footer') }}</p>
</body>
</html>
