<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    @php
        // Même mécanique de marque que les autres documents PDF du dépôt
        // (`pdf.receipt`, `pdf.retail-receipt`) : logo du tenant si exploitable,
        // couleur primaire pour les en-têtes. Sans branding, le rendu reste
        // strictement identique.
        $pdfBrandLogo = \App\Support\PdfBranding::logoPath($company ?? null);
        $pdfBrandColor = \App\Support\PdfBranding::primaryColor($company ?? null, '');
    @endphp
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 17px; margin: 0 0 6px; }
        h2 { font-size: 12px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #666; }
        .right { text-align: right; }
        .total { font-weight: bold; background: #f9f9f9; }
        .meta td { border: none; padding: 2px 0; }
        @if($pdfBrandColor !== '') th { background: {{ $pdfBrandColor }}; color: #ffffff; } @endif
    </style>
</head>
<body>
    @if($pdfBrandLogo !== null)
    <img src="{{ $pdfBrandLogo }}" alt="" style="height: 44px; margin-bottom: 6px;">
    @endif

    <h1>{{ __('pdf.edu_fee_batch_receipt_title') }}</h1>
    <div class="muted">
        {{ $company?->name ?? '' }}@if($company?->city) — {{ $company->city }}@endif
    </div>

    <table class="meta" style="margin-top: 10px;">
        <tr>
            <td><strong>{{ __('pdf.edu_fee_batch_reference') }}</strong> {{ $batchReference }}</td>
            <td><strong>{{ __('pdf.edu_fee_batch_date') }}</strong> {{ $paidAt }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('pdf.edu_fee_batch_method') }}</strong> {{ __('pdf.edu_fee_method_'.$method) }}</td>
            <td><strong>{{ __('pdf.edu_fee_batch_lines') }}</strong> {{ count($lines) }}</td>
        </tr>
        @if($reference !== null && $reference !== '')
        <tr>
            <td colspan="2"><strong>{{ __('pdf.edu_fee_batch_bordereau') }}</strong> {{ $reference }}</td>
        </tr>
        @endif
    </table>

    <h2>{{ __('pdf.edu_fee_batch_details') }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 22%;">{{ __('pdf.edu_fee_student_number') }}</th>
                <th>{{ __('pdf.edu_fee_student_name') }}</th>
                <th style="width: 24%;">{{ __('pdf.edu_fee_type') }}</th>
                <th class="right" style="width: 18%;">{{ __('pdf.edu_fee_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
            <tr>
                <td>{{ $line['student_number'] }}</td>
                <td>{{ $line['student_name'] }}</td>
                <td>{{ $line['fee_type'] }}</td>
                <td class="right">{{ $line['amount'] }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">{{ __('pdf.edu_fee_batch_total') }}</td>
                <td class="right">{{ $total }}</td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 14px;">{{ __('pdf.edu_fee_batch_footer') }}</p>
</body>
</html>
