<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;

/**
 * CEDEAO (#2158) — déclarations sociales mensuelles Burkina Faso (CNSS)
 * et Mali (INPS) au format CSV.
 *
 * Une ligne par bulletin validé : matricule (CNSS BF / INPS ML), nom,
 * prénom, salaire brut, assiette retraite plafonnée, retraite salariale,
 * retraite patronale, prestations familiales patronales, risques
 * professionnels patronaux (AT), totaux salarial/patronal par ligne +
 * ligne TOTAUX.
 *
 * Taux et plafonds alignés sur CedeaoPayrollRules::socialContributions()
 * (issue #1829, docs BF_COMPLIANCE.md §4 / ML_COMPLIANCE.md §4) :
 *   - BF : retraite 5,5 % / 6,5 %, famille 7,0 % — plafond 900 000 XOF ;
 *     AT 3,5 % non plafonné ;
 *   - ML : retraite 3,6 % / 7,4 % — plafond 3 000 000 XOF ; famille 4,0 %
 *     et AT 2,0 % non plafonnés.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable local
 * (registre docs/payroll/VALIDATION_EXPERTE.md, #1904).
 */
class CedeaoCnsDeclarationGenerator
{
    /** BF — plafond retraite/famille CNSS (XOF/mois). */
    public const BF_RETIREMENT_FAMILY_CAP = 900000.0;

    public const BF_RATE_RETRAITE_EMP = 5.5;

    public const BF_RATE_RETRAITE_PAT = 6.5;

    public const BF_RATE_FAMILLE_PAT = 7.0;

    public const BF_RATE_AT_PAT = 3.5;

    /** ML — plafond retraite INPS (XOF/mois) ; famille/AT non plafonnés. */
    public const ML_RETIREMENT_CAP = 3000000.0;

    public const ML_RATE_RETRAITE_EMP = 3.6;

    public const ML_RATE_RETRAITE_PAT = 7.4;

    public const ML_RATE_FAMILLE_PAT = 4.0;

    public const ML_RATE_AT_PAT = 2.0;

    public function generate(PayrollRun $run): string
    {
        $country = $run->country_code;
        $matriculeColumn = $country === 'ML' ? 'inps_ml_matricule' : 'cnss_bf_matricule';

        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,'.$matriculeColumn)
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
            $matricule = $employee->{$matriculeColumn} ?? null;
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
     * Calcul des cotisations CNSS BF / INPS ML pour un bulletin — source
     * unique de vérité des lignes ET des totaux (aligné sur
     * CedeaoPayrollRules::calculateSocialCharges()).
     *
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float}
     */
    private function contributionFor(PaySlip $slip, string $country): array
    {
        $gross = (float) $slip->gross_salary;

        if ($country === 'ML') {
            $retirementBase = min($gross, self::ML_RETIREMENT_CAP);

            $retraiteEmp = round($retirementBase * self::ML_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($retirementBase * self::ML_RATE_RETRAITE_PAT / 100, 2);
            // Famille 4,0 % et AT 2,0 % NON plafonnés (INPS Mali, #1829).
            $famillePat = round($gross * self::ML_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::ML_RATE_AT_PAT / 100, 2);
        } else {
            // BF : retraite + famille plafonnées à 900 000 XOF, AT non plafonné.
            $retirementBase = min($gross, self::BF_RETIREMENT_FAMILY_CAP);

            $retraiteEmp = round($retirementBase * self::BF_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($retirementBase * self::BF_RATE_RETRAITE_PAT / 100, 2);
            $famillePat = round($retirementBase * self::BF_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::BF_RATE_AT_PAT / 100, 2);
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
