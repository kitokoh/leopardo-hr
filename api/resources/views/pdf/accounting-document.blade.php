<!doctype html>
@php
    // Issue #5227 — rendu RTL : police arabe (Almarai) + shaping des chaînes
    // arabes (formes contextuelles + inversion) pour dompdf, même pattern
    // que le bulletin de paie (issue #5242). Les textes latins passent
    // inchangés ($shape est l'identité hors RTL).
    $shape = static fn (string $value): string => $rtl
        ? \App\Modules\Payroll\Infrastructure\Pdf\ArabicPdfText::shape($value)
        : $value;
    $t = static fn (string $key, array $replace = []): string => $shape((string) __($key, $replace));
@endphp
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: {{ $rtl ? 'Almarai' : 'DejaVu Sans' }}, 'DejaVu Sans', sans-serif; color: #172033; font-size: 11px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #334155; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .company { font-size: 14px; font-weight: bold; }
        .company small { display: block; font-weight: normal; color: #64748b; font-size: 10px; }
        .doc-meta { text-align: {{ $rtl ? 'left' : 'right' }}; }
        .doc-meta .type { font-size: 15px; font-weight: bold; color: #0f766e; }
        .doc-meta div { margin-top: 2px; }
        .parties { display: flex; justify-content: space-between; margin-top: 14px; }
        .parties .to { text-align: {{ $rtl ? 'left' : 'right' }}; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d9dee8; padding: 5px 6px; text-align: left; }
        @if($rtl) th, td { text-align: right; } @endif
        th { background: #eef4f1; font-size: 10px; }
        td.num, th.num { text-align: right; }
        .totals { margin-top: 12px; margin-left: auto; width: 45%; }
        .totals td { border: none; padding: 3px 6px; text-align: right; }
        .totals .grand { font-weight: bold; border-top: 2px solid #172033; }
        .note { margin-top: 16px; padding: 8px; background: #fff7ed; border: 1px solid #fed7aa; font-size: 10px; }
        .footer { margin-top: 20px; font-size: 9px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #e2e8f0; color: #475569; }
        .status-draft, .status-partially_paid { background: #fef9c3; color: #854d0e; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold;
                 background: #f1f5f9; color: #334155; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">
            {{ $company->name }}
            @if (! empty($company->address))
                <small>{{ $company->address }}</small>
            @endif
            @if (! empty($company->phone) || ! empty($company->email))
                <small>{{ trim(($company->phone ?? '').' · '.($company->email ?? ''), ' ·') }}</small>
            @endif
        </div>
        <div class="doc-meta">
            <div class="type">{{ $shape($document_type_label) }}</div>
            <div><span class="badge">{{ $shape($status_label) }}</span></div>
            <div><span class="muted">{{ $t('accounting.number') }}:</span> {{ $document->number }}</div>
            <div><span class="muted">{{ $t('accounting.issue_date') }}:</span> {{ $document->issue_date?->format('d/m/Y') }}</div>
            @if ($document->due_date)
                <div><span class="muted">{{ $t('accounting.due_date') }}:</span> {{ $document->due_date->format('d/m/Y') }}</div>
            @endif
            @if ($document->delivery_date)
                <div><span class="muted">{{ $t('accounting.delivery_date') }}:</span> {{ $document->delivery_date->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    <div class="parties">
        <div>
            <div class="muted">{{ $t('accounting.from') }}</div>
            <div><strong>{{ $company->name }}</strong></div>
            @if (! empty($company->address))
                <div>{{ $company->address }}</div>
            @endif
        </div>
        @if ($contact)
            <div class="to">
                <div class="muted">{{ $t('accounting.to') }}</div>
                <div><strong>{{ $contact->name }}</strong></div>
                @if (! empty($contact->tax_id))
                    <div>{{ $t('accounting.nif') }}: {{ $contact->tax_id }}</div>
                @endif
                @if (! empty($contact->address))
                    <div>{{ $contact->address }}</div>
                @endif
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ $t('accounting.description') }}</th>
                <th class="num">{{ $t('accounting.quantity') }}</th>
                <th class="num">{{ $t('accounting.unit_price') }}</th>
                <th class="num">{{ $t('accounting.discount') }}</th>
                <th class="num">{{ $t('accounting.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ number_format($line['unit_price'], 2, ',', ' ') }}</td>
                    <td class="num">{{ $line['discount'] > 0 ? number_format($line['discount'], 2, ',', ' ') : '—' }}</td>
                    <td class="num">{{ number_format($line['amount'], 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">{{ $t('accounting.no_lines') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">{{ $t('accounting.subtotal_ht') }}</td>
            <td>{{ number_format($totals['subtotal_ht'], 2, ',', ' ') }} {{ $document->currency ?? '' }}</td>
        </tr>
        <tr>
            <td class="muted">{{ $t('accounting.tax') }}{{ $totals['tva_rate'] !== null ? ' ('.$totals['tva_rate'].' %)' : '' }}</td>
            <td>{{ number_format($totals['tax_amount'], 2, ',', ' ') }} {{ $document->currency ?? '' }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $t('accounting.total_ttc') }}</td>
            <td>{{ number_format($totals['total_ttc'], 2, ',', ' ') }} {{ $document->currency ?? '' }}</td>
        </tr>
        @if ($totals['paid_amount'] > 0)
            <tr>
                <td class="muted">{{ $t('accounting.paid') }}</td>
                <td>{{ number_format($totals['paid_amount'], 2, ',', ' ') }} {{ $document->currency ?? '' }}</td>
            </tr>
            <tr>
                <td class="muted">{{ $t('accounting.remaining') }}</td>
                <td>{{ number_format($totals['remaining'], 2, ',', ' ') }} {{ $document->currency ?? '' }}</td>
            </tr>
        @endif
    </table>

    @if ($document->notes)
        <div class="note">{{ $document->notes }}</div>
    @endif

    @if ($legal_mentions)
        <div class="note">
            <strong>{{ $t('accounting.legal_mentions') }}</strong><br>
            {{ $legal_mentions }}
        </div>
    @endif

    <div class="footer">
        {{ $company->name }} — {{ $shape($document_type_label) }} {{ $document->number }}
    </div>
</body>
</html>
