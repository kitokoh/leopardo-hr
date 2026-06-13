<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Travail</title>
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
        <h1>{{ $company->name ?? 'L\'Entreprise' }}</h1>
        <p>{{ $company->address ?? '' }}</p>
    </div>

    <div class="title">
        CONTRAT DE TRAVAIL - {{ strtoupper($contract->contract_type ?? 'CDI') }}
    </div>

    <div class="section">
        <p>Entre les soussignés :</p>
        <p><strong>{{ $company->name ?? 'L\'Entreprise' }}</strong>, agissant en qualité d'employeur,</p>
        <p>Et</p>
        <p><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>, ci-après dénommé(e) le Salarié,</p>
        <p>Il a été convenu ce qui suit :</p>
    </div>

    <div class="section">
        <div class="section-title">Article 1 : Engagement</div>
        <p>Le Salarié est engagé à compter du <strong>{{ \Carbon\Carbon::parse($contract->start_date)->format('d/m/Y') }}</strong> en qualité de <strong>{{ $contract->job_title }}</strong>.</p>
        @if($contract->end_date)
            <p>Ce contrat est conclu pour une durée déterminée, prenant fin le <strong>{{ \Carbon\Carbon::parse($contract->end_date)->format('d/m/Y') }}</strong>.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Article 2 : Rémunération</div>
        <p>En contrepartie de ses services, le Salarié percevra une rémunération de base de <strong>{{ number_format($contract->base_salary, 2, ',', ' ') }} {{ $contract->currency ?? 'DZD' }}</strong> ({{ $contract->salary_frequency ?? 'mensuel' }}).</p>
    </div>

    <div class="section">
        <div class="section-title">Article 3 : Durée du travail</div>
        <p>Le Salarié sera soumis à un temps de travail de <strong>{{ $contract->work_hours_per_week ?? '40' }} heures par semaine</strong>.</p>
    </div>

    <table class="signatures">
        <tr>
            <td>
                Fait à ......................., le .......................<br><br>
                <strong>Pour l'employeur</strong><br>
                (Signature et cachet)
            </td>
            <td>
                Fait à ......................., le .......................<br><br>
                <strong>Le Salarié</strong><br>
                (Signature précédée de la mention "Lu et approuvé")
            </td>
        </tr>
    </table>

</body>
</html>
