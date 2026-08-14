<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;

/**
 * CEMAC/CM (issue #1823) — déclaration CNPS mensuelle camerounaise, format
 * DAS (Déclaration et Attestation de Salaires), export CSV directement
 * déposable auprès de la CNPS Cameroun.
 *
 * Une ligne par bulletin VALIDÉ du run : matricule CNPS employé, nom,
 * prénom, salaire brut, assiette plafonnée (min(brut, 750 000 XAF)),
 * cotisation vieillesse salariale, vieillesse patronale, famille patronale,
 * AT patronale, total patronal. Ligne TOTAUX en fin de fichier pour le
 * contrôle comptable.
 *
 * Les taux/plafonds sont résolus depuis CemacPayrollRules (CM, pilot —
 * issue #1821) : CNPS_CM_VIE_EMP 4,2 % / CNPS_CM_VIE_PAT 4,2 % /
 * CNPS_CM_FAM_PAT 7,0 % (plafond 750 000 XAF/mois) + CNPS_CM_AT_PAT 2,0 %
 * non plafonné — une seule source de vérité, pas de constantes dupliquées.
 */
class CnpsDeclarationGenerator
{
    /** Plafond CNPS Cameroun (XAF/mois) — miroir de CemacPayrollRules (CM). */
    private const CNPS_CAP = 750000.0;

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,cnps_matricule'])
            ->where('status', 'validated')
            ->get();

        $rates = $this->rateMap();
        $total = $this->totals($run);

        $header = [
            'matricule_cnps', 'nom', 'prenom', 'salaire_brut', 'assiette_plafonnee',
            'vieillesse_salariale', 'vieillesse_patronale', 'famille_patronale',
            'at_patronale', 'total_patronal',
        ];
        $rows = [$header];

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $base = min($gross, self::CNPS_CAP);

            $rows[] = [
                (string) ($slip->employee->cnps_matricule ?? $slip->employee->matricule ?? $slip->employee_id),
                trim((string) ($slip->employee->last_name ?? '')),
                trim((string) ($slip->employee->first_name ?? '')),
                number_format($gross, 2, '.', ''),
                number_format($base, 2, '.', ''),
                number_format(round($base * $rates['CNPS_CM_VIE_EMP'] / 100, 2), 2, '.', ''),
                number_format(round($base * $rates['CNPS_CM_VIE_PAT'] / 100, 2), 2, '.', ''),
                number_format(round($base * $rates['CNPS_CM_FAM_PAT'] / 100, 2), 2, '.', ''),
                number_format(round($gross * $rates['CNPS_CM_AT_PAT'] / 100, 2), 2, '.', ''),
                number_format(
                    round($base * $rates['CNPS_CM_VIE_PAT'] / 100, 2)
                        + round($base * $rates['CNPS_CM_FAM_PAT'] / 100, 2)
                        + round($gross * $rates['CNPS_CM_AT_PAT'] / 100, 2),
                    2, '.', ''
                ),
            ];
        }

        $rows[] = [
            'TOTAL',
            "{$slips->count()} bulletins",
            '',
            number_format($total['assiette'], 2, '.', ''),
            number_format($total['assiette_plafonnee'], 2, '.', ''),
            number_format($total['vieillesse_salariale'], 2, '.', ''),
            number_format($total['vieillesse_patronale'], 2, '.', ''),
            number_format($total['famille_patronale'], 2, '.', ''),
            number_format($total['at_patronale'], 2, '.', ''),
            number_format($total['total_patronal'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Totaux de contrôle de la déclaration (mêmes règles que generate()).
     *
     * @return array{
     *   employee_count: int, assiette: float, assiette_plafonnee: float,
     *   vieillesse_salariale: float, vieillesse_patronale: float,
     *   famille_patronale: float, at_patronale: float, total_patronal: float
     * }
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->where('status', 'validated')->get();
        $rates = $this->rateMap();

        $totals = [
            'employee_count' => $slips->count(),
            'assiette' => 0.0,
            'assiette_plafonnee' => 0.0,
            'vieillesse_salariale' => 0.0,
            'vieillesse_patronale' => 0.0,
            'famille_patronale' => 0.0,
            'at_patronale' => 0.0,
            'total_patronal' => 0.0,
        ];

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $base = min($gross, self::CNPS_CAP);

            $vieillesseSalariale = round($base * $rates['CNPS_CM_VIE_EMP'] / 100, 2);
            $vieillessePatronale = round($base * $rates['CNPS_CM_VIE_PAT'] / 100, 2);
            $famillePatronale = round($base * $rates['CNPS_CM_FAM_PAT'] / 100, 2);
            $atPatronale = round($gross * $rates['CNPS_CM_AT_PAT'] / 100, 2);

            $totals['assiette'] += $gross;
            $totals['assiette_plafonnee'] += $base;
            $totals['vieillesse_salariale'] += $vieillesseSalariale;
            $totals['vieillesse_patronale'] += $vieillessePatronale;
            $totals['famille_patronale'] += $famillePatronale;
            $totals['at_patronale'] += $atPatronale;
            $totals['total_patronal'] += $vieillessePatronale + $famillePatronale + $atPatronale;
        }

        $rounded = [];
        foreach ($totals as $key => $value) {
            $rounded[$key] = $key === 'employee_count' ? (int) $value : round((float) $value, 2);
        }

        return $rounded;
    }

    /**
     * @return array<string, float>
     */
    private function rateMap(): array
    {
        $rates = [];

        foreach ((new CemacPayrollRules)->forMemberCountry('CM')->socialContributions() as $contribution) {
            $rates[$contribution['code']] = (float) $contribution['rate'];
        }

        return $rates;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = array_map(static function (array $row): string {
            return implode(',', array_map(static function ($cell): string {
                $cell = (string) $cell;

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
