<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Support\CsvCellSanitizer;

/**
 * CEMAC/CM (#1823) — déclaration CNPS mensuelle camerounaise (format DAS).
 *
 * Une ligne par bulletin validé du run : matricule CNPS, nom, prénom,
 * salaire brut, assiette plafonnée (min(brut, plafond CNPS CM)), cotisation
 * vieillesse salariale, vieillesse patronale, famille patronale, AT
 * patronale (non plafonnée), total patronal. Ligne de totaux en fin de
 * fichier pour contrôle (CM_COMPLIANCE.md §2).
 *
 * Les taux et le plafond ne sont PAS dupliqués ici : ils sont résolus depuis
 * `CemacPayrollRules` (forMemberCountry('CM'), asOf(period du run)) — la
 * source de vérité unique du moteur de paie, au même titre que le calcul
 * des bulletins (PA2-ARCH-004 : relecture d'un run passé = taux effectifs
 * de sa propre période).
 *
 * Matricule : `cnps_matricule` employé, repli sur `matricule` interne puis
 * sur l'id du bulletin si l'employé n'a aucun matricule.
 *
 * ⚠️ Format à valider avec un comptable camerounais — structure interne
 * documentée, comme la CNAS DZ.
 */
class CnpsDeclarationGenerator
{
    /**
     * Taux et plafonds CNPS CM résolus depuis les règles pays (CEMAC/CM) —
     * codes `CNPS_CM_*` déclarés par CemacPayrollRules::socialContributions().
     *
     * @return array<string, array{rate: float, cap: float|null}>
     */
    private function contributionRates(PayrollRun $run): array
    {
        $resolved = [];

        $rules = (new CemacPayrollRules)
            ->forMemberCountry('CM')
            ->asOf($run->period_start);

        foreach ($rules->socialContributions() as $contribution) {
            $resolved[$contribution['code']] = [
                'rate' => (float) $contribution['rate'],
                'cap' => $contribution['cap'] === null ? null : (float) $contribution['cap'],
            ];
        }

        return $resolved;
    }

    /**
     * @return string CSV complet : en-tête + une ligne par bulletin + totaux
     */
    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,matricule,cnps_matricule')
            ->where('status', 'validated')
            ->get();

        $rates = $this->contributionRates($run);

        // Plafond statutaire CNPS CM (vieillesse + famille) : la vieillesse
        // et la famille partagent le même cap dans CemacPayrollRules ; l'AT
        // (2 %) n'est pas plafonnée (cap null → assiette = brut entier).
        $ceiling = $rates['CNPS_CM_VIE_EMP']['cap'] ?? PHP_FLOAT_MAX;

        $header = [
            'matricule_cnps', 'nom', 'prenom', 'salaire_brut',
            'assiette_plafonnee', 'vieillesse_salariale', 'vieillesse_patronale',
            'famille_patronale', 'at_patronale', 'total_patronal',
        ];
        $rows = [$header];

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, $ceiling);

            $vieillesseEmp = round($cappedBase * $rates['CNPS_CM_VIE_EMP']['rate'] / 100, 2);
            $vieillessePat = round($cappedBase * $rates['CNPS_CM_VIE_PAT']['rate'] / 100, 2);
            $famillePat = round($cappedBase * $rates['CNPS_CM_FAM_PAT']['rate'] / 100, 2);
            // L'AT (2 %) n'est pas plafonnée : assiette = brut entier.
            $atPat = round($gross * $rates['CNPS_CM_AT_PAT']['rate'] / 100, 2);
            $totalPat = round($vieillessePat + $famillePat + $atPat, 2);

            $employee = $slip->employee;

            $rows[] = [
                (string) ($employee->cnps_matricule ?? $employee->matricule ?? $slip->employee_id),
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
     * Totaux de contrôle sur les bulletins validés du run (mêmes règles
     * que generate(), taux résolus depuis CemacPayrollRules).
     *
     * @return array{gross: float, capped_base: float, vieillesse_emp: float, vieillesse_pat: float, famille_pat: float, at_pat: float, total_patronal: float, slip_count: int}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->where('status', 'validated')->get();

        $rates = $this->contributionRates($run);
        $ceiling = $rates['CNPS_CM_VIE_EMP']['cap'] ?? PHP_FLOAT_MAX;

        $gross = 0.0;
        $cappedBase = 0.0;
        $vieillesseEmp = 0.0;
        $vieillessePat = 0.0;
        $famillePat = 0.0;
        $atPat = 0.0;

        foreach ($slips as $slip) {
            $slipGross = (float) $slip->gross_salary;
            $slipCapped = min($slipGross, $ceiling);

            $gross += $slipGross;
            $cappedBase += $slipCapped;
            $vieillesseEmp += round($slipCapped * $rates['CNPS_CM_VIE_EMP']['rate'] / 100, 2);
            $vieillessePat += round($slipCapped * $rates['CNPS_CM_VIE_PAT']['rate'] / 100, 2);
            $famillePat += round($slipCapped * $rates['CNPS_CM_FAM_PAT']['rate'] / 100, 2);
            $atPat += round($slipGross * $rates['CNPS_CM_AT_PAT']['rate'] / 100, 2);
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
                $cell = CsvCellSanitizer::neutralize((string) $cell);

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
