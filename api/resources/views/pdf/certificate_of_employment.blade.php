<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Certificat de travail</title>
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
    <h1>Certificat de travail</h1>
    <div class="sub">{{ $company?->address ?? '' }} — {{ $company?->city ?? '' }} {{ $company?->country ?? '' }}</div>

    <div class="meta">
        <div><strong>Employé :</strong> {{ $employee->first_name }} {{ $employee->last_name }}</div>
        <div><strong>Matricule :</strong> {{ $employee->matricule ?? $employee->id }}</div>
        <div><strong>Fonction :</strong> {{ $employee->position ?? 'Employé' }}</div>
        <div><strong>Date d'embauche :</strong> {{ $employee->contract_start?->format('d/m/Y') ?? '—' }}</div>
        <div><strong>Ancienneté :</strong> {{ $months_of_service }} mois</div>
    </div>

    <div class="content">
        <p>
            Nous soussignés, {{ $company?->name ?? 'la société' }}, certifions que
            <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>,
            matricule {{ $employee->matricule ?? $employee->id }},
            a été employé(e) au sein de notre entreprise du
            {{ $employee->contract_start?->format('d/m/Y') ?? '—' }}
            au {{ $settlement['end_date'] ?? '—' }} en qualité de
            {{ $employee->position ?? 'employé(e)' }}.
        </p>
        <p>
            Ce certificat est délivré à l'intéressé(e) pour faire valoir ce que de droit.
        </p>
    </div>

    <table>
        <tr><th>Élément du solde de tout compte</th><th>Montant ({{ $company?->currency ?? 'DZD' }})</th></tr>
        <tr><td>Prorata du salaire du dernier mois</td><td>{{ number_format($settlement['breakdown']['prorated_pay'], 2) }}</td></tr>
        <tr><td>Indemnité de congés payés non pris</td><td>{{ number_format($settlement['breakdown']['leave_indemnity'], 2) }}</td></tr>
        <tr><td>Indemnité de préavis</td><td>{{ number_format($settlement['breakdown']['notice_pay'], 2) }}</td></tr>
        <tr><td>Indemnité d'ancienneté</td><td>{{ number_format($settlement['breakdown']['severance'], 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td><strong>{{ number_format($settlement['breakdown']['total'], 2) }}</strong></td></tr>
    </table>

    <div class="signature">
        <div>Fait à {{ $company?->city ?? '—' }}, le {{ now()->format('d/m/Y') }}.</div>
        <div style="margin-top: 40px;">Signature et cachet de l'employeur :</div>
    </div>

    <div class="footer">Document généré par Leopardo RH — {{ $company?->name ?? '' }}</div>
</body>
</html>
