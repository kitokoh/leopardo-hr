<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;

/**
 * CEDEAO (#1830) — déclaration CNSS mensuelle Côte d'Ivoire (CSV).
 *
 * Une ligne par bulletin validé : matricule CNSS, nom, prénom, salaire
 * brut, assiette plafonnée (min(brut, 1 647 315 XOF)), retraite salariale
 * (3,2 %), retraite patronale (4,5 %), famille patronale (5,75 %), AT
 * patronale (2,0 %), totaux salarial/patronal par ligne + ligne TOTAUX.
 *
 * ⚠️ Issue #2539 : les TAUX et PLAFONDS sont lus depuis
 * CedeaoPayrollRules('CI')::socialContributions() (source unique) — toute
 * constante locale dupliquée a été supprimée ; un changement de taux dans
 * les règles pays est automatiquement répercuté dans le CSV.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable ivoirien.
 */
class CnssDeclarationGenerator
{
    public function __construct(
        private readonly CedeaoPayrollRules $rules = new CedeaoPayrollRules('CI'),
    ) {}

    /**
     * Rate lookup depuis les règles pays (issue #2539) — codes CI canoniques.
     *
     * @return array{rate: float, cap: float|null}
     */
    private function contribution(string $code): array
    {
        foreach ($this->rules->socialContributions() as $contrib) {
            if ($contrib['code'] === $code) {
                return [
                    'rate' => (float) $contrib['rate'],
                    'cap' => isset($contrib['cap']) ? (float) $contrib['cap'] : null,
                ];
            }
        }

        throw new \RuntimeException("CnssDeclarationGenerator: code {$code} absent de CedeaoPayrollRules::socialContributions().");
    }

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

        $totals = [
            'gross' => 0.0,
            'capped_base' => 0.0,
            'retraite_emp' => 0.0,
            'retraite_pat' => 0.0,
            'famille_pat' => 0.0,
            'at_pat' => 0.0,
        ];

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip);

            $totals['gross'] += $contrib['gross'];
            $totals['capped_base'] += $contrib['capped_base'];
            $totals['retraite_emp'] += $contrib['retraite_emp'];
            $totals['retraite_pat'] += $contrib['retraite_pat'];
            $totals['famille_pat'] += $contrib['famille_pat'];
            $totals['at_pat'] += $contrib['at_pat'];

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
        $slips = $run->paySlips()->where('status', 'validated')->get();

        $gross = 0.0;
        $cappedBase = 0.0;
        $retraiteEmp = 0.0;
        $retraitePat = 0.0;
        $famillePat = 0.0;
        $atPat = 0.0;

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip);

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
     * Calcul des cotisations CNSS CI pour un bulletin — source unique de
     * vérité des lignes ET des totaux (aucune dérive possible).
     *
     * @return array{gross: float, capped_base: float, retraite_emp: float, retraite_pat: float, famille_pat: float, at_pat: float, total_emp: float, total_pat: float}
     */
    private function contributionFor(PaySlip $slip): array
    {
        $gross = (float) $slip->gross_salary;

        // Issue #2539 : taux/plafonds depuis les règles pays (source unique).
        $retraite = $this->contribution('CNSS_CI_RET_EMP');
        $retraitePat = $this->contribution('CNSS_CI_RET_PAT');
        $famille = $this->contribution('CNSS_CI_FAM_PAT');
        $at = $this->contribution('CNSS_CI_AT_PAT');

        $retirementBase = min($gross, $retraite['cap'] ?? $gross);
        // #1913 : famille et AT/MP plafonnées séparément à 70 000 XOF/mois
        // (guide CNPS) — aligné sur CedeaoPayrollRules::calculateSocialCharges.
        $familyAtBase = min($gross, $famille['cap'] ?? $gross);

        $retraiteEmp = round($retirementBase * $retraite['rate'] / 100, 2);
        $retraitePatAmt = round($retirementBase * $retraitePat['rate'] / 100, 2);
        $famillePat = round($familyAtBase * $famille['rate'] / 100, 2);
        $atPatAmt = round($familyAtBase * $at['rate'] / 100, 2);
        $totalEmp = round($retraiteEmp, 2);
        $totalPat = round($retraitePatAmt + $famillePat + $atPatAmt, 2);

        return [
            'gross' => $gross,
            'capped_base' => $retirementBase,
            'retraite_emp' => $retraiteEmp,
            'retraite_pat' => $retraitePatAmt,
            'famille_pat' => $famillePat,
            'at_pat' => $atPatAmt,
            'total_emp' => $totalEmp,
            'total_pat' => $totalPat,
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
