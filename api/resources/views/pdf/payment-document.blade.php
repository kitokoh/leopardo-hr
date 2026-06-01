<!doctype html>
<html lang="fr">
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
        <p class="title">Document de paiement</p>
        <p class="muted">{{ $company?->name ?? 'Leopardo RH' }} - Reference #{{ $document->id }}</p>
    </div>

    <div class="box">
        <strong>Collaborateur</strong><br>
        {{ trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')) ?: 'Non renseigne' }}<br>
        <span class="muted">{{ $employee?->email }}</span>
    </div>

    <table>
        <tr><th>Type</th><td>{{ $document->document_type }}</td></tr>
        <tr><th>Statut</th><td>{{ $document->status }}</td></tr>
        <tr><th>Periode</th><td>{{ $payrollRun?->period_start?->toDateString() }} - {{ $payrollRun?->period_end?->toDateString() }}</td></tr>
        <tr><th>Montant net</th><td>{{ number_format((float) ($paySlip?->net_salary ?? ($metadata['amount'] ?? 0)), 2, ',', ' ') }}</td></tr>
        <tr><th>Genere le</th><td>{{ now()->format('Y-m-d H:i') }}</td></tr>
    </table>

    <p class="muted">Document genere automatiquement par Leopardo RH. Les validations et confirmations restent tracees dans l'application.</p>
</body>
</html>
