<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;

/**
 * CEMAC (#2155) — déclarations sociales mensuelles Gabon (CNSS) et
 * Congo-Brazzaville (CNSS) au format CSV.
 *
 * Une ligne par bulletin validé : matricule CNPS, nom, prénom, salaire
 * brut, assiette retraite plafonnée, retraite salariale, retraite
 * patronale, prestations familiales patronales, risques professionnels
 * patronaux (AT), totaux salarial/patronal par ligne + ligne TOTAUX.
 *
 * Taux et plafonds alignés sur CemacPayrollRules::socialContributions()
 * (issue #1824, docs GA_COMPLIANCE.md §3 / CG_COMPLIANCE.md §3) :
 *   - GA : retraite 2,5 % / 5,0 %, famille 8,0 % — plafond 3 000 000 XAF ;
 *     AT 3,0 % non plafonné ;
 *   - CG : retraite 4,0 % / 8,0 %, famille 10,0 % — plafond 2 500 000 XAF ;
 *     AT 3,0 % non plafonné.
 * Les centimes additionnels CM (2,2 % / 3,6 %) sont EXCLUS pour GA/CG.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable local
 * (registre docs/payroll/VALIDATION_EXPERTE.md, #1904).
 */
class CemacCnpsDeclarationGenerator
{
    /** GA — plafond retraite/famille CNSS (XAF/mois). */
    public const GA_RETIREMENT_FAMILY_CAP = 3000000.0;

    public const GA_RATE_RETRAITE_EMP = 2.5;

    public const GA_RATE_RETRAITE_PAT = 5.0;

    public const GA_RATE_FAMILLE_PAT = 8.0;

    public const GA_RATE_AT_PAT = 3.0;

    /** CG — plafond retraite/famille CNSS (XAF/mois) ; AT non plafonné. */
    public const CG_RETIREMENT_FAMILY_CAP = 2500000.0;

    public const CG_RATE_RETRAITE_EMP = 4.0;

    public const CG_RATE_RETRAITE_PAT = 8.0;

    public const CG_RATE_FAMILLE_PAT = 10.0;

    public const CG_RATE_AT_PAT = 3.0;

    public function generate(PayrollRun $run): string
    {
        $country = $run->country_code;

        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,matricule,cnps_matricule')
            ->where('status', 'validated')
            ->get();

        $header = [
            'matricule', 'nom', 'prenom', 'salaire_brut', 'assiette_retraite',
            'retraite_salariale', 'retraite_patronale', 'famille_patronale',
            'at_patronale', 'total_salarial', 'total_patronal',
        ];
        $rows = [$header];

        $totals = [
            'gross' => 0.0,
            'capped_base' => 0.0,
            'retraite_emp' => 0.0,
            'retraite_pat' => 0.0,
            'famille_pat' => 0.0,
            'at_pat' => 0.0,
        ];

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip, $country);

            $totals['gross'] += $contrib['gross'];
            $totals['capped_base'] += $contrib['capped_base'];
            $totals['retraite_emp'] += $contrib['retraite_emp'];
            $totals['retraite_pat'] += $contrib['retraite_pat'];
            $totals['famille_pat'] += $contrib['famille_pat'];
            $totals['at_pat'] += $contrib['at_pat'];

            $employee = $slip->employee;

            /** @var string|null $matricule */
            $matricule = $employee->cnps_matricule ?? $employee->matricule ?? null;
            /** @var string|null $lastName */
            $lastName = $employee->last_name ?? null;
            /** @var string|null $firstName */
            $firstName = $employee->first_name ?? null;

            $rows[] = [
                (string) ($matricule ?? ''),
                (string) ($lastName ?? ''),
                (string) ($firstName ?? ''),
                number_format($contrib['gross'], 2, '.', ''),
                number_format($contrib['capped_base'], 2, '.', ''),
                number_format($contrib['retraite_emp'], 2, '.', ''),
                number_format($contrib['retraite_pat'], 2, '.', ''),
                number_format($contrib['famille_pat'], 2, '.', ''),
                number_format($contrib['at_pat'], 2, '.', ''),
                number_format($contrib['total_emp'], 2, '.', ''),
                number_format($contrib['total_pat'], 2, '.', ''),
            ];
        }

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
            number_format($totals['retraite_emp'], 2, '.', ''),
            number_format($totals['retraite_pat'] + $totals['famille_pat'] + $totals['at_pat'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Calcul des cotisations CNSS GA/CG pour un bulletin — source unique
     * de vérité des lignes ET des totaux (aligné sur
     * CemacPayrollRules::calculateSocialCharges()).
     *
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float}
     */
    private function contributionFor(PaySlip $slip, string $country): array
    {
        $gross = (float) $slip->gross_salary;

        if ($country === 'GA') {
            $retirementBase = min($gross, self::GA_RETIREMENT_FAMILY_CAP);

            $retraiteEmp = round($retirementBase * self::GA_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($retirementBase * self::GA_RATE_RETRAITE_PAT / 100, 2);
            $famillePat = round($retirementBase * self::GA_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::GA_RATE_AT_PAT / 100, 2);
        } else {
            $retirementBase = min($gross, self::CG_RETIREMENT_FAMILY_CAP);

            $retraiteEmp = round($retirementBase * self::CG_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($retirementBase * self::CG_RATE_RETRAITE_PAT / 100, 2);
            $famillePat = round($retirementBase * self::CG_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::CG_RATE_AT_PAT / 100, 2);
        }

        return [
            'gross' => $gross,
            'capped_base' => $retirementBase,
            'retraite_emp' => $retraiteEmp,
            'retraite_pat' => $retraitePat,
            'famille_pat' => $famillePat,
            'at_pat' => $atPat,
            'total_emp' => $retraiteEmp,
            'total_pat' => round($retraitePat + $famillePat + $atPat, 2),
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

                // CSV injection (#1922) : neutralisation des formules/DDE.
                if ($cell !== '' && str_contains("=+-@\t\r\n", $cell[0])) {
                    $cell = "'".$cell;
                }

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
