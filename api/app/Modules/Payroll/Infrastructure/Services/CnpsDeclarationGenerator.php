<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * CEMAC/CM (#1823) — déclaration CNPS mensuelle camerounaise (format DAS).
 *
 * Une ligne par bulletin validé du run : matricule CNPS, nom, prénom,
 * salaire brut, assiette plafonnée (min(brut, 750 000 XAF)), cotisation
 * vieillesse salariale (4,2 %), vieillesse patronale (4,2 %), famille
 * patronale (7,0 %), AT patronale (2,0 % non plafonnée), total patronal.
 * Ligne de totaux en fin de fichier pour contrôle (CM_COMPLIANCE.md §2).
 *
 * ⚠️ Format à valider avec un comptable camerounais — structure interne
 * documentée, comme la CNAS DZ.
 */
class CnpsDeclarationGenerator
{
    public const CNPS_CAP = 750000.0;

    public const RATE_VIEILLESSE_EMP = 4.2;

    public const RATE_VIEILLESSE_PAT = 4.2;

    public const RATE_FAMILLE_PAT = 7.0;

    public const RATE_AT_PAT = 2.0;

    /**
     * @return string CSV complet : en-tête + une ligne par bulletin + totaux
     */
    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,cnps_matricule')
            ->where('status', 'validated')
            ->get();

        $header = [
            'matricule_cnps', 'nom', 'prenom', 'salaire_brut',
            'assiette_plafonnee', 'vieillesse_salariale', 'vieillesse_patronale',
            'famille_patronale', 'at_patronale', 'total_patronal',
        ];
        $rows = [$header];

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNPS_CAP);

            $vieillesseEmp = round($cappedBase * self::RATE_VIEILLESSE_EMP / 100, 2);
            $vieillessePat = round($cappedBase * self::RATE_VIEILLESSE_PAT / 100, 2);
            $famillePat = round($cappedBase * self::RATE_FAMILLE_PAT / 100, 2);
            // L'AT (2 %) n'est pas plafonnée : assiette = brut entier.
            $atPat = round($gross * self::RATE_AT_PAT / 100, 2);
            $totalPat = round($vieillessePat + $famillePat + $atPat, 2);

            $employee = $slip->employee;

            $rows[] = [
                (string) ($employee->cnps_matricule ?? ''),
                (string) ($employee->last_name ?? ''),
                (string) ($employee->first_name ?? ''),
                number_format($gross, 2, '.', ''),
                number_format($cappedBase, 2, '.', ''),
                number_format($vieillesseEmp, 2, '.', ''),
                number_format($vieillessePat, 2, '.', ''),
                number_format($famillePat, 2, '.', ''),
                number_format($atPat, 2, '.', ''),
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
            number_format($totals['vieillesse_emp'], 2, '.', ''),
            number_format($totals['vieillesse_pat'], 2, '.', ''),
            number_format($totals['famille_pat'], 2, '.', ''),
            number_format($totals['at_pat'], 2, '.', ''),
            number_format($totals['total_patronal'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Totaux de contrôle sur les bulletins validés du run.
     *
     * @return array{gross: float, capped_base: float, vieillesse_emp: float, vieillesse_pat: float, famille_pat: float, at_pat: float, total_patronal: float, slip_count: int}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->where('status', 'validated')->get();

        $gross = 0.0;
        $cappedBase = 0.0;
        $vieillesseEmp = 0.0;
        $vieillessePat = 0.0;
        $famillePat = 0.0;
        $atPat = 0.0;

        foreach ($slips as $slip) {
            $slipGross = (float) $slip->gross_salary;
            $slipCapped = min($slipGross, self::CNPS_CAP);

            $gross += $slipGross;
            $cappedBase += $slipCapped;
            $vieillesseEmp += round($slipCapped * self::RATE_VIEILLESSE_EMP / 100, 2);
            $vieillessePat += round($slipCapped * self::RATE_VIEILLESSE_PAT / 100, 2);
            $famillePat += round($slipCapped * self::RATE_FAMILLE_PAT / 100, 2);
            $atPat += round($slipGross * self::RATE_AT_PAT / 100, 2);
        }

        return [
            'gross' => round($gross, 2),
            'capped_base' => round($cappedBase, 2),
            'vieillesse_emp' => round($vieillesseEmp, 2),
            'vieillesse_pat' => round($vieillessePat, 2),
            'famille_pat' => round($famillePat, 2),
            'at_pat' => round($atPat, 2),
            'total_patronal' => round($vieillessePat + $famillePat + $atPat, 2),
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
