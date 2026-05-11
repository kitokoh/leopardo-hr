<!doctype html>
<html lang="fr">
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
    <div class="company-name">{{ $company->name ?? 'Entreprise' }}</div>
    <div style="font-size: 10px; color: #666;">
        {{ $company->address ?? '' }}
        @if(!empty($company->city)) — {{ $company->city }} @endif
        @if(!empty($company->country)) ({{ $company->country }}) @endif
    </div>

    <div class="title">Bulletin de Paie</div>
    <div class="period">Période : {{ $slip->period_start->format('d/m/Y') }} — {{ $slip->period_end->format('d/m/Y') }}</div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Employé</div>
            <div class="info-value">{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</div>
            <div class="info-label">Matricule</div>
            <div class="info-value">#{{ $employee->id }}</div>
        </div>
        <div class="info-col">
            <div class="info-label">Jours travaillés</div>
            <div class="info-value">{{ $slip->actual_days_worked ?? $slip->working_days ?? 22 }} / {{ $slip->working_days ?? 22 }}</div>
            <div class="info-label">Heures supplémentaires</div>
            <div class="info-value">{{ $slip->overtime_hours ?? 0 }}h</div>
        </div>
    </div>

    {{-- Earnings --}}
    <div class="section-title">Rémunérations</div>
    <table>
        <thead>
            <tr>
                <th>Libellé</th>
                <th>Base</th>
                <th>Taux</th>
                <th class="amount">Montant</th>
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
    <div class="section-title">Cotisations et retenues salariales</div>
    <table>
        <thead>
            <tr>
                <th>Libellé</th>
                <th>Base</th>
                <th>Taux</th>
                <th class="amount">Montant</th>
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
    <div class="section-title">Cotisations patronales (pour information)</div>
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
            <td>Salaire brut</td>
            <td class="amount">{{ number_format($slip->gross_salary, 2, ',', ' ') }} {{ $currency }}</td>
        </tr>
        <tr class="total-row">
            <td>Total retenues</td>
            <td class="amount">{{ number_format($slip->total_deductions, 2, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>

    <div class="net-box">
        <div class="net-label">NET À PAYER</div>
        <div class="net-amount">{{ number_format($slip->net_salary, 2, ',', ' ') }} {{ $currency }}</div>
    </div>

    <div class="footer">
        <div>Document généré le {{ now()->format('d/m/Y à H:i') }} — Leopardo RH</div>
        <div>Ce bulletin de paie est un document officiel. Conservez-le sans limitation de durée.</div>
        @if(!empty($legalMentions))
        <div style="margin-top: 6px;">{{ $legalMentions }}</div>
        @endif
    </div>
</body>
</html>
