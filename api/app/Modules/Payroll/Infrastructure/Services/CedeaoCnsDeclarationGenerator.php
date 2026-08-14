<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;

/**
 * CEDEAO (#2158) — déclaration CNSS mensuelle Burkina Faso (BF) / INPS
 * mensuelle Mali (ML) au format CSV.
 *
 * Une ligne par bulletin validé : matricule CNSS/INPS, nom, prénom, salaire
 * brut, assiette plafonnée, retraite salariale, retraite patronale, famille
 * patronale, AT patronale, totaux salarial/patronal par ligne + ligne
 * TOTAUX. Assiettes et taux alignés sur
 * `CedeaoPayrollRules::calculateSocialCharges()` (issue #1829) :
 *
 *  - BF (CNSS) : retraite 5,5 % salarié / 6,5 % patronal, famille 7,0 %
 *    patronal — plafonnées à 900 000 XOF/mois ; AT 3,5 % patronal non
 *    plafonné (BF_COMPLIANCE.md §3).
 *  - ML (INPS) : retraite 3,6 % salarié / 7,4 % patronal plafonnées à
 *    3 000 000 XOF/mois ; famille 4,0 % + AT 2,0 % patronaux non plafonnés
 *    (ML_COMPLIANCE.md §3).
 *
 * ⚠️ Format interne documenté — à valider avec un comptable CNSS/INPS local.
 */
class CedeaoCnsDeclarationGenerator
{
    public const BF_CAP = 900000.0;

    public const BF_RATE_RETRAITE_EMP = 5.5;

    public const BF_RATE_RETRAITE_PAT = 6.5;

    public const BF_RATE_FAMILLE_PAT = 7.0;

    public const BF_RATE_AT_PAT = 3.5;

    public const ML_CAP = 3000000.0;

    public const ML_RATE_RETRAITE_EMP = 3.6;

    public const ML_RATE_RETRAITE_PAT = 7.4;

    public const ML_RATE_FAMILLE_PAT = 4.0;

    public const ML_RATE_AT_PAT = 2.0;

    public function generate(PayrollRun $run): string
    {
        $countryCode = strtoupper((string) $run->country_code);

        if (! in_array($countryCode, ['BF', 'ML'], true)) {
            throw new \InvalidArgumentException("CedeaoCnsDeclarationGenerator ne supporte que BF/ML, reçu {$countryCode}.");
        }

        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,matricule')
            ->where('status', 'validated')
            ->get();

        $header = [
            'matricule', 'nom', 'prenom', 'salaire_brut', 'assiette_plafonnee',
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
            $contrib = $this->contributionFor($slip, $countryCode);

            $totals['gross'] += $contrib['gross'];
            $totals['capped_base'] += $contrib['capped_base'];
            $totals['retraite_emp'] += $contrib['retraite_emp'];
            $totals['retraite_pat'] += $contrib['retraite_pat'];
            $totals['famille_pat'] += $contrib['famille_pat'];
            $totals['at_pat'] += $contrib['at_pat'];

            $employee = $slip->employee;

            /** @var string|null $matricule */
            $matricule = $employee->matricule ?? null;
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
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float, slip_count: int}
     */
    public function totals(PayrollRun $run): array
    {
        $countryCode = strtoupper((string) $run->country_code);
        $slips = $run->paySlips()->where('status', 'validated')->get();

        $gross = 0.0;
        $cappedBase = 0.0;
        $retraiteEmp = 0.0;
        $retraitePat = 0.0;
        $famillePat = 0.0;
        $atPat = 0.0;

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip, $countryCode);

            $gross += $contrib['gross'];
            $cappedBase += $contrib['capped_base'];
            $retraiteEmp += $contrib['retraite_emp'];
            $retraitePat += $contrib['retraite_pat'];
            $famillePat += $contrib['famille_pat'];
            $atPat += $contrib['at_pat'];
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
     * Calcul des cotisations CNSS BF / INPS ML pour un bulletin — source
     * unique de vérité des lignes ET des totaux (aucune dérive possible).
     *
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float}
     */
    private function contributionFor(PaySlip $slip, string $countryCode): array
    {
        $gross = (float) $slip->gross_salary;

        if ($countryCode === 'BF') {
            $base = min($gross, self::BF_CAP);
            $retraiteEmp = round($base * self::BF_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($base * self::BF_RATE_RETRAITE_PAT / 100, 2);
            // #1829 : famille plafonnée (900 000), AT non plafonné (pilote).
            $famillePat = round($base * self::BF_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::BF_RATE_AT_PAT / 100, 2);
        } else { // ML
            $base = min($gross, self::ML_CAP);
            $retraiteEmp = round($base * self::ML_RATE_RETRAITE_EMP / 100, 2);
            $retraitePat = round($base * self::ML_RATE_RETRAITE_PAT / 100, 2);
            // #1829 : famille et AT non plafonnés (pilote).
            $famillePat = round($gross * self::ML_RATE_FAMILLE_PAT / 100, 2);
            $atPat = round($gross * self::ML_RATE_AT_PAT / 100, 2);
        }

        return [
            'gross' => $gross,
            'capped_base' => $base,
            'retraite_emp' => $retraiteEmp,
            'retraite_pat' => $retraitePat,
            'famille_pat' => $famillePat,
            'at_pat' => $atPat,
            'total_emp' => round($retraiteEmp, 2),
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

                // CSV injection (#1922) : un champ commençant par =, +, -, @,
                // tab ou saut de ligne est neutralisé (préfixe ') pour qu'Excel
                // / LibreOffice ne l'interprète pas comme une formule/DDE.
                if ($cell !== '' && str_contains("=+-@\t\r\n", $cell[0])) {
                    $cell = "'".$cell;
                }

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
