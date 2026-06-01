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
        <p class="title">Recu d'avance sur salaire</p>
        <p class="muted">{{ $company?->name ?? 'Leopardo RH' }} - Reference #{{ $document->id }}</p>
    </div>

    <div class="box">
        <strong>Collaborateur</strong><br>
        {{ trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')) ?: 'Non renseigne' }}<br>
        <span class="muted">{{ $employee?->email }}</span>
    </div>

    <table>
        <tr><th>Montant</th><td>{{ number_format((float) ($salaryAdvance?->amount ?? $metadata['amount'] ?? 0), 2, ',', ' ') }}</td></tr>
        <tr><th>Motif</th><td>{{ $salaryAdvance?->reason ?? 'Non renseigne' }}</td></tr>
        <tr><th>Reference paiement</th><td>{{ $salaryAdvance?->payment_reference ?? ($metadata['payment_reference'] ?? 'Non renseignee') }}</td></tr>
        <tr><th>Paiement declare le</th><td>{{ $salaryAdvance?->payment_declared_at?->format('Y-m-d H:i') ?? ($metadata['payment_declared_at'] ?? 'Non renseigne') }}</td></tr>
        <tr><th>Statut validation</th><td>{{ $salaryAdvance?->validation_status ?? 'payment_declared' }}</td></tr>
    </table>

    <p class="muted">Ce recu confirme la declaration de paiement. La confirmation de reception par l'employe reste disponible dans le journal de validation.</p>
</body>
</html>
