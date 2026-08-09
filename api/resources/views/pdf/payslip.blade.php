<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .company-name { font-size: 16px; font-weight: bold; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 20px 0 10px; text-transform: uppercase; }
        .period { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; }
        .section-title { font-size: 12px; font-weight: bold; background: #e8e8e8; padding: 4px 8px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { padding: 4px 8px; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: bold; font-size: 10px; text-transform: uppercase; color: #555; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; border-top: 2px solid #333; }
        .net-box { background: #f0f7ff; border: 2px solid #2563eb; padding: 12px; text-align: center; margin: 20px 0; }
        .net-label { font-size: 12px; color: #555; }
        .net-amount { font-size: 22px; font-weight: bold; color: #2563eb; }
        .footer { margin-top: 30px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .info-label { font-size: 9px; color: #888; text-transform: uppercase; }
        .info-value { font-size: 11px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="company-name">{{ $company->name ?? __('pdf.payslip_company_fallback') }}</div>
    <div style="font-size: 10px; color: #666;">
        {{ $company->address ?? '' }}
        @if(!empty($company->city)) — {{ $company->city }} @endif
        @if(!empty($company->country)) ({{ $company->country }}) @endif
    </div>
    @if(!empty($companyLegal))
    <div style="font-size: 9px; color: #555; margin-top: 4px;">
        @foreach($companyLegal as $label => $value) <span>{{ $label }} : {{ $value }}</span>@if(!$loop->last) · @endif @endforeach
    </div>
    @endif

    <div class="title">{{ __('pdf.payslip_title') }}</div>
    <div class="period">{{ __('pdf.period') }} : {{ $slip->period_start->format('d/m/Y') }} — {{ $slip->period_end->format('d/m/Y') }}</div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">{{ __('pdf.employee') }}</div>
            <div class="info-value">{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</div>
            <div class="info-label">{{ __('pdf.payslip_matricule') }}</div>
            <div class="info-value">#{{ $employee->id }}</div>
        </div>
        <div class="info-col">
            <div class="info-label">{{ __('pdf.payslip_worked_days') }}</div>
            <div class="info-value">{{ $slip->actual_days_worked ?? $slip->working_days ?? 22 }} / {{ $slip->working_days ?? 22 }}</div>
            <div class="info-label">{{ __('pdf.overtime_hours') }}</div>
            <div class="info-value">{{ $slip->overtime_hours ?? 0 }}h</div>
        </div>
    </div>

    {{-- Earnings --}}
    <div class="section-title">{{ __('pdf.payslip_earnings_section') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('pdf.payslip_column_label') }}</th>
                <th>{{ __('pdf.payslip_column_base') }}</th>
                <th>{{ __('pdf.payslip_column_rate') }}</th>
                <th class="amount">{{ __('pdf.payslip_column_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines->where('type', 'earning') as $line)
            <tr>
                <td>{{ $line->name }}</td>
                <td>{{ number_format($line->base_amount, 2, ',', ' ') }}</td>
                <td>{{ $line->rate ? number_format($line->rate, 2).'%' : '—' }}</td>
                <td class="amount">{{ number_format($line->amount, 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Deductions --}}
    <div class="section-title">{{ __('pdf.payslip_deductions_section') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('pdf.payslip_column_label') }}</th>
                <th>{{ __('pdf.payslip_column_base') }}</th>
                <th>{{ __('pdf.payslip_column_rate') }}</th>
                <th class="amount">{{ __('pdf.payslip_column_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines->where('type', 'deduction') as $line)
            <tr>
                <td>{{ $line->name }}</td>
                <td>{{ number_format($line->base_amount, 2, ',', ' ') }}</td>
                <td>{{ $line->rate ? number_format($line->rate, 2).'%' : '—' }}</td>
                <td class="amount">{{ number_format($line->amount, 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Employer contributions --}}
    <div class="section-title">{{ __('pdf.payslip_employer_contributions_section') }}</div>
    <table>
        <tbody>
            @foreach($lines->where('type', 'employer_contribution') as $line)
            <tr>
                <td>{{ $line->name }}</td>
                <td>{{ number_format($line->base_amount, 2, ',', ' ') }}</td>
                <td>{{ $line->rate ? number_format($line->rate, 2).'%' : '—' }}</td>
                <td class="amount">{{ number_format($line->amount, 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Summary --}}
    <table style="margin-top: 10px;">
        <tr class="total-row">
            <td>{{ __('pdf.payslip_gross_salary') }}</td>
            <td class="amount">{{ number_format($slip->gross_salary, 2, ',', ' ') }} {{ $currency }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('pdf.payslip_total_deductions') }}</td>
            <td class="amount">{{ number_format($slip->total_deductions, 2, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>

    @if(($annualCumuls['gross'] ?? 0) > 0)
    <div class="section-title">{{ __('pdf.payslip_annual_cumuls') }}</div>
    <table>
        <tr>
            <th>{{ __('pdf.payslip_gross_salary') }}</th>
            <th>{{ __('pdf.payslip_total_deductions') }}</th>
            <th>{{ __('pdf.payslip_net_to_pay') }}</th>
        </tr>
        <tr>
            <td class="amount">{{ number_format($annualCumuls['gross'], 2, ',', ' ') }} {{ $currency }}</td>
            <td class="amount">{{ number_format($annualCumuls['deductions'], 2, ',', ' ') }} {{ $currency }}</td>
            <td class="amount">{{ number_format($annualCumuls['net'], 2, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>
    @endif

    <div class="net-box">
        <div class="net-label">{{ __('pdf.payslip_net_to_pay') }}</div>
        <div class="net-amount">{{ number_format($slip->net_salary, 2, ',', ' ') }} {{ $currency }}</div>
    </div>

    <div class="footer">
        <div>{{ __('pdf.payslip_generated_on', ['date' => now()->format('d/m/Y H:i')]) }} — Leopardo RH</div>
        <div>{{ __('pdf.payslip_official_notice') }}</div>
        @if(!empty($legalMentions))
        <div style="margin-top: 6px;">{{ $legalMentions }}</div>
        @endif
    </div>
</body>
</html>
