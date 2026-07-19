<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('pdf.contract_title', ['type' => strtoupper($contract->contract_type ?? 'CDI')]) }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; text-align: center; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; text-decoration: underline; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .info-table td.label { font-weight: bold; width: 30%; }
        .signatures { margin-top: 50px; width: 100%; }
        .signatures td { width: 50%; text-align: center; padding-top: 50px; border-top: 1px dashed #999; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $company->name ?? __('pdf.contract_company_fallback') }}</h1>
        <p>{{ $company->address ?? '' }}</p>
    </div>

    <div class="title">
        {{ __('pdf.contract_title', ['type' => strtoupper($contract->contract_type ?? 'CDI')]) }}
    </div>

    <div class="section">
        <p>{{ __('pdf.contract_parties_intro') }}</p>
        <p><strong>{{ $company->name ?? __('pdf.contract_company_fallback') }}</strong>, {{ __('pdf.contract_employer_role') }}</p>
        <p>{{ __('pdf.contract_and') }}</p>
        <p><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>, {{ __('pdf.contract_employee_role') }}</p>
        <p>{{ __('pdf.contract_agreement_intro') }}</p>
    </div>

    <div class="section">
        <div class="section-title">{{ __('pdf.contract_article1_title') }}</div>
        <p>{!! __('pdf.contract_article1_body', ['start_date' => '<strong>'.\Carbon\Carbon::parse($contract->start_date)->format('d/m/Y').'</strong>', 'job_title' => '<strong>'.$contract->job_title.'</strong>']) !!}</p>
        @if($contract->end_date)
            <p>{!! __('pdf.contract_article1_fixed_term', ['end_date' => '<strong>'.\Carbon\Carbon::parse($contract->end_date)->format('d/m/Y').'</strong>']) !!}</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">{{ __('pdf.contract_article2_title') }}</div>
        <p>{!! __('pdf.contract_article2_body', ['amount' => '<strong>'.number_format($contract->base_salary, 2, ',', ' ').' '.($contract->currency ?? 'DZD').'</strong>', 'frequency' => $contract->salary_frequency ?? __('pdf.contract_frequency_monthly')]) !!}</p>
    </div>

    <div class="section">
        <div class="section-title">{{ __('pdf.contract_article3_title') }}</div>
        <p>{!! __('pdf.contract_article3_body', ['hours' => '<strong>'.($contract->work_hours_per_week ?? '40').'</strong>']) !!}</p>
    </div>

    <table class="signatures">
        <tr>
            <td>
                {{ __('pdf.contract_signature_place_date') }}<br><br>
                <strong>{{ __('pdf.contract_signature_employer') }}</strong><br>
                {{ __('pdf.contract_signature_employer_note') }}
            </td>
            <td>
                {{ __('pdf.contract_signature_place_date') }}<br><br>
                <strong>{{ __('pdf.contract_signature_employee') }}</strong><br>
                {{ __('pdf.contract_signature_employee_note') }}
            </td>
        </tr>
    </table>

</body>
</html>
