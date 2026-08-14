<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * CEDEAO (#1830) — déclaration CNSS mensuelle Côte d'Ivoire (CSV).
 *
 * Une ligne par bulletin validé : matricule CNSS, nom, prénom, salaire
 * brut, assiette plafonnée (min(brut, 1 647 315 XOF)), retraite salariale
 * (3,2 %), retraite patronale (4,5 %), famille patronale (5,75 %), AT
 * patronale (2,0 %), totaux salarial/patronal par ligne + ligne TOTAUX.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable ivoirien.
 */
class CnssDeclarationGenerator
{
    public const CNSS_CI_CAP = 1647315.0;

    public const RATE_RETRAITE_EMP = 3.2;

    public const RATE_RETRAITE_PAT = 4.5;

    public const RATE_FAMILLE_PAT = 5.75;

    public const RATE_AT_PAT = 2.0;

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,cnss_ci_matricule')
            ->where('status', 'validated')
            ->get();

        $header = [
            'matricule_cnss', 'nom', 'prenom', 'salaire_brut', 'assiette_plafonnee',
            'retraite_salariale', 'retraite_patronale', 'famille_patronale',
            'at_patronale', 'total_salarial', 'total_patronal',
        ];
        $rows = [$header];

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNSS_CI_CAP);

            $retraiteEmp = round($cappedBase * self::RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($cappedBase * self::RATE_RETRAITE_PAT / 100, 2);
            $famillePat = round($cappedBase * self::RATE_FAMILLE_PAT / 100, 2);
            // BUG #1898 : l'AT patronal (2,0 %) est NON plafonné (comme le moteur
            // calculateSocialCharges — cap => null) — la déclaration le plafonnait
            // à tort, faussant le total patronal au-delà de 1 647 315 XOF.
            $atPat = round($gross * self::RATE_AT_PAT / 100, 2);
            $totalEmp = round($retraiteEmp, 2);
            $totalPat = round($retraitePat + $famillePat + $atPat, 2);

            $employee = $slip->employee;

            /** @var string|null $matricule */
            $matricule = $employee->cnss_ci_matricule ?? null;
            /** @var string|null $lastName */
            $lastName = $employee->last_name ?? null;
            /** @var string|null $firstName */
            $firstName = $employee->first_name ?? null;

            $rows[] = [
                (string) ($matricule ?? ''),
                (string) ($lastName ?? ''),
                (string) ($firstName ?? ''),
                number_format($gross, 2, '.', ''),
                number_format($cappedBase, 2, '.', ''),
                number_format($retraiteEmp, 2, '.', ''),
                number_format($retraitePat, 2, '.', ''),
                number_format($famillePat, 2, '.', ''),
                number_format($atPat, 2, '.', ''),
                number_format($totalEmp, 2, '.', ''),
                number_format($totalPat, 2, '.', ''),
            ];
        }

        $totals = $this->totals($run);

        $rows[] = [
            'TOTAL',
            "{$slips->count()} bulletins",
            '',
            number_format($totals['gross'], 2, '.', ''),
            number_format($totals['capped_base'], 2, '.', ''),
            number_format($totals['retraite_emp'], 2, '.', ''),
            number_format($totals['retraite_pat'], 2, '.', ''),
            number_format($totals['famille_pat'], 2, '.', ''),
            number_format($totals['at_pat'], 2, '.', ''),
            number_format($totals['total_emp'], 2, '.', ''),
            number_format($totals['total_pat'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float, slip_count: int}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->where('status', 'validated')->get();

        $gross = 0.0;
        $cappedBase = 0.0;
        $retraiteEmp = 0.0;
        $retraitePat = 0.0;
        $famillePat = 0.0;
        $atPat = 0.0;

        foreach ($slips as $slip) {
            $slipGross = (float) $slip->gross_salary;
            $slipCapped = min($slipGross, self::CNSS_CI_CAP);

            $gross += $slipGross;
            $cappedBase += $slipCapped;
            $retraiteEmp += round($slipCapped * self::RATE_RETRAITE_EMP / 100, 2);
            $retraitePat += round($slipCapped * self::RATE_RETRAITE_PAT / 100, 2);
            $famillePat += round($slipCapped * self::RATE_FAMILLE_PAT / 100, 2);
            $atPat += round($slipCapped * self::RATE_AT_PAT / 100, 2);
        }

        return [
            'gross' => round($gross, 2),
            'capped_base' => round($cappedBase, 2),
            'retraite_emp' => round($retraiteEmp, 2),
            'retraite_pat' => round($retraitePat, 2),
            'famille_pat' => round($famillePat, 2),
            'at_pat' => round($atPat, 2),
            'total_emp' => round($retraiteEmp, 2),
            'total_pat' => round($retraitePat + $famillePat + $atPat, 2),
            'slip_count' => $slips->count(),
        ];
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
