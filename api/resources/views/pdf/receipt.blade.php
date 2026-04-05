<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #666; }
        .disclaimer { margin-top: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reçu de période (estimation)</h1>
    <div class="muted">{{ $company->name }} — {{ $company->city }} — {{ $company->country }}</div>

    <h2>Employé</h2>
    <div>
        #{{ $employee->id }} — {{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }}
    </div>

    <h2>Période</h2>
    <div>{{ $estimate['period']['from'] }} → {{ $estimate['period']['to'] }}</div>

    <h2>Détails</h2>
    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>Heures</th>
            <th>HS</th>
            <th>Base</th>
            <th>HS</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($estimate['breakdown'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ number_format($row['hours'], 2) }}</td>
                <td>{{ number_format($row['overtime_hours'], 2) }}</td>
                <td>{{ number_format($row['base_gain'], 2) }}</td>
                <td>{{ number_format($row['overtime_gain'], 2) }}</td>
                <td>{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Totaux</h2>
    <div>Brut estimé: {{ number_format($estimate['totals']['gross'], 2) }} {{ $estimate['currency'] }}</div>
    <div>Déductions estimées: {{ number_format($estimate['totals']['deductions'], 2) }} {{ $estimate['currency'] }}</div>
    <div>Net estimé: {{ number_format($estimate['totals']['net'], 2) }} {{ $estimate['currency'] }}</div>

    <div class="disclaimer">CE DOCUMENT N'EST PAS UN BULLETIN DE PAIE OFFICIEL</div>
</body>
</html>

