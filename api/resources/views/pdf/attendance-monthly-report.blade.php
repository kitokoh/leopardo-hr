<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d9dee8; padding: 6px; text-align: left; }
        th { background: #eef4f1; }
        .muted { color: #64748b; }
        .metrics { margin-top: 16px; }
        .metric { display: inline-block; width: 23%; margin-right: 1%; padding: 8px; background: #f6f8fb; }
        .metric strong { display: block; font-size: 16px; }
    </style>
</head>
<body>
    <h1>Rapport mensuel de pointage</h1>
    <div class="muted">{{ $report['company']['name'] }} - {{ $report['period']['date_from'] }} au {{ $report['period']['date_to'] }}</div>

    <div class="metrics">
        <div class="metric"><span>Employes</span><strong>{{ $report['totals']['employees'] }}</strong></div>
        <div class="metric"><span>Heures</span><strong>{{ $report['totals']['worked_hours'] }}</strong></div>
        <div class="metric"><span>Heures sup</span><strong>{{ $report['totals']['overtime_hours'] }}</strong></div>
        <div class="metric"><span>Retard min</span><strong>{{ $report['totals']['late_minutes'] }}</strong></div>
    </div>

    <h2>Detail par employe</h2>
    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Heures</th>
                <th>Heures sup</th>
                <th>Retard min</th>
                <th>Sorties manquantes</th>
                <th>Corrections</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['employees'] as $employee)
                <tr>
                    <td>{{ $employee['matricule'] }}</td>
                    <td>{{ $employee['name'] }}</td>
                    <td>{{ $employee['worked_hours'] }}</td>
                    <td>{{ $employee['overtime_hours'] }}</td>
                    <td>{{ $employee['late_minutes'] }}</td>
                    <td>{{ $employee['missing_check_outs'] }}</td>
                    <td>{{ $employee['manual_corrections'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
