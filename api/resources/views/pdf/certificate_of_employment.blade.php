<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.cert_title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 18px; text-align: center; text-transform: uppercase; margin-bottom: 6px; }
        .sub { text-align: center; font-size: 12px; color: #555; margin-bottom: 30px; }
        .company { text-align: center; font-weight: bold; margin-bottom: 4px; }
        .meta { margin-bottom: 28px; }
        .meta div { margin-bottom: 6px; }
        .content p { line-height: 1.7; text-align: justify; }
        .signature { margin-top: 60px; }
        .signature div { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td, th { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .footer { margin-top: 40px; font-size: 11px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="company">{{ $company?->name ?? '' }}</div>
    <h1>{{ __('pdf.cert_title') }}</h1>
    <div class="sub">{{ $company?->address ?? '' }} — {{ $company?->city ?? '' }} {{ $company?->country ?? '' }}</div>

    <div class="meta">
        <div><strong>{{ __('pdf.cert_employee_label') }}</strong> {{ $employee->first_name }} {{ $employee->last_name }}</div>
        <div><strong>{{ __('pdf.cert_matricule_label') }}</strong> {{ $employee->matricule ?? $employee->id }}</div>
        <div><strong>{{ __('pdf.cert_position_label') }}</strong> {{ $employee->position ?? __('pdf.cert_position_fallback') }}</div>
        <div><strong>{{ __('pdf.cert_hire_date_label') }}</strong> {{ $employee->contract_start?->format('d/m/Y') ?? '—' }}</div>
        <div><strong>{{ __('pdf.cert_seniority_label') }}</strong> {{ $months_of_service }} {{ __('pdf.cert_months_suffix') }}</div>
    </div>

    <div class="content">
        <p>
            {{ __('pdf.cert_we_hereby') }} {{ $company?->name ?? __('pdf.cert_company_fallback') }}{{ __('pdf.cert_certify_that') }}
            <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>,
            matricule {{ $employee->matricule ?? $employee->id }},
            {{ __('pdf.cert_was_employed') }}
            {{ $employee->contract_start?->format('d/m/Y') ?? '—' }}
            {{ __('pdf.cert_to') }} {{ $settlement['end_date'] ?? '—' }} {{ __('pdf.cert_as') }}
            {{ $employee->position ?? __('pdf.cert_employee_fallback') }}.
        </p>
        <p>
            {{ __('pdf.cert_issued_for') }}
        </p>
    </div>

    <table>
        <tr><th>{{ __('pdf.cert_settlement_title') }}</th><th>{{ __('pdf.cert_amount_prefix') }}{{ $company?->currency ?? 'DZD' }})</th></tr>
        <tr><td>{{ __('pdf.cert_prorated_pay') }}</td><td>{{ number_format($settlement['breakdown']['prorated_pay'], 2) }}</td></tr>
        <tr><td>{{ __('pdf.cert_leave_indemnity') }}</td><td>{{ number_format($settlement['breakdown']['leave_indemnity'], 2) }}</td></tr>
        <tr><td>{{ __('pdf.cert_notice_pay') }}</td><td>{{ number_format($settlement['breakdown']['notice_pay'], 2) }}</td></tr>
        <tr><td>{{ __('pdf.cert_severance') }}</td><td>{{ number_format($settlement['breakdown']['severance'], 2) }}</td></tr>
        <tr><td><strong>{{ __('pdf.cert_total') }}</strong></td><td><strong>{{ number_format($settlement['breakdown']['total'], 2) }}</strong></td></tr>
    </table>

    <div class="signature">
        <div>{{ __('pdf.cert_made_in') }} {{ $company?->city ?? '—' }}{{ __('pdf.cert_on_date') }} {{ now()->format('d/m/Y') }}.</div>
        <div style="margin-top: 40px;">Signature et cachet de l'employeur :</div>
    </div>

    <div class="footer">Document généré par Leopardo RH — {{ $company?->name ?? '' }}</div>
</body>
</html>
