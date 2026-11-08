<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;

/**
 * CEDEAO (#1830) — déclaration IPRES/CSS mensuelle Sénégal (CSV).
 *
 * Une ligne par bulletin validé : matricule IPRES, nom, prénom, catégorie
 * (general/cadre), brut, assiette T1 (min(brut, 432 000 XOF)), cotisation
 * T1 salariale (5,6 %) / patronale (8,4 %), assiette T2 (cadres
 * uniquement : min(brut, 2 160 000) − 432 000), cotisation T2 salariale
 * (2,4 %) / patronale (3,6 %), CSS famille patronale (7,0 % plafonnée à
 * 63 000 XOF/mois — taux officiel CIPRES/CLEISS #2473, aligné moteur, #1913)
 * + ligne TOTAUX.
 *
 * ⚠️ Issue #2539 : les TAUX et PLAFONDS sont lus depuis
 * SenegalPayrollRules::socialContributions() (source unique) — toute
 * constante locale dupliquée a été supprimée ; un changement de taux dans
 * les règles pays est automatiquement répercuté dans le CSV.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable sénégalais.
 */
class IpresDeclarationGenerator
{
    public function __construct(
        private readonly SenegalPayrollRules $rules = new SenegalPayrollRules,
    ) {}

    /**
     * Rate lookup depuis les règles pays (issue #2539) — codes SN canoniques.
     *
     * @return array{rate: float, cap: float|null, floor: float|null, ceiling: float|null}
     */
    private function contribution(string $code): array
    {
        foreach ($this->rules->socialContributions() as $contrib) {
            if ($contrib['code'] === $code) {
                return [
                    'rate' => (float) $contrib['rate'],
                    'cap' => isset($contrib['cap']) ? (float) $contrib['cap'] : null,
                    'floor' => isset($contrib['floor']) ? (float) $contrib['floor'] : null,
                    'ceiling' => isset($contrib['ceiling']) ? (float) $contrib['ceiling'] : null,
                ];
            }
        }

        throw new \RuntimeException("IpresDeclarationGenerator: code {$code} absent de SenegalPayrollRules::socialContributions().");
    }

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,ipres_matricule,ipres_category')
            ->where('status', 'validated')
            ->get();

        $header = [
            'matricule_ipres', 'nom', 'prenom', 'categorie', 'brut',
            'assiette_t1', 't1_salariale', 't1_patronale',
            'assiette_t2', 't2_salariale', 't2_patronale',
            'css_famille_patronale', 'total_patronal',
        ];
        $rows = [$header];

        $totals = [
            'gross' => 0.0,
            't1_base' => 0.0,
            't1_emp' => 0.0,
            't1_pat' => 0.0,
            't2_base' => 0.0,
            't2_emp' => 0.0,
            't2_pat' => 0.0,
            'css_famille_pat' => 0.0,
        ];

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip);

            $totals['gross'] += $contrib['gross'];
            $totals['t1_base'] += $contrib['t1_base'];
            $totals['t1_emp'] += $contrib['t1_emp'];
            $totals['t1_pat'] += $contrib['t1_pat'];
            $totals['t2_base'] += $contrib['t2_base'];
            $totals['t2_emp'] += $contrib['t2_emp'];
            $totals['t2_pat'] += $contrib['t2_pat'];
            $totals['css_famille_pat'] += $contrib['css_famille_pat'];

            $employee = $slip->employee;

            /** @var string|null $matricule */
            $matricule = $employee->ipres_matricule ?? null;
            /** @var string|null $lastName */
            $lastName = $employee->last_name ?? null;
            /** @var string|null $firstName */
            $firstName = $employee->first_name ?? null;
            $isCadre = ($employee->ipres_category ?? 'general') === 'cadre';

            $rows[] = [
                (string) ($matricule ?? ''),
                (string) ($lastName ?? ''),
                (string) ($firstName ?? ''),
                $isCadre ? 'cadre' : 'general',
                number_format($contrib['gross'], 2, '.', ''),
                number_format($contrib['t1_base'], 2, '.', ''),
                number_format($contrib['t1_emp'], 2, '.', ''),
                number_format($contrib['t1_pat'], 2, '.', ''),
                number_format($contrib['t2_base'], 2, '.', ''),
                number_format($contrib['t2_emp'], 2, '.', ''),
                number_format($contrib['t2_pat'], 2, '.', ''),
                number_format($contrib['css_famille_pat'], 2, '.', ''),
                number_format($contrib['total_patronal'], 2, '.', ''),
            ];
        }

        $rows[] = [
            'TOTAL',
            "{$slips->count()} bulletins",
            '',
            '',
            number_format($totals['gross'], 2, '.', ''),
            number_format($totals['t1_base'], 2, '.', ''),
            number_format($totals['t1_emp'], 2, '.', ''),
            number_format($totals['t1_pat'], 2, '.', ''),
            number_format($totals['t2_base'], 2, '.', ''),
            number_format($totals['t2_emp'], 2, '.', ''),
            number_format($totals['t2_pat'], 2, '.', ''),
            number_format($totals['css_famille_pat'], 2, '.', ''),
            number_format($totals['t1_pat'] + $totals['t2_pat'] + $totals['css_famille_pat'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * @return array{gross: float, t1_base: float, t1_emp: float, t1_pat: float, t2_base: float, t2_emp: float, t2_pat: float, css_famille_pat: float, total_patronal: float, slip_count: int}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->with('employee:id,ipres_category')->where('status', 'validated')->get();

        $gross = 0.0;
        $t1Base = 0.0;
        $t1Emp = 0.0;
        $t1Pat = 0.0;
        $t2Base = 0.0;
        $t2Emp = 0.0;
        $t2Pat = 0.0;
        $cssFamillePat = 0.0;

        foreach ($slips as $slip) {
            $contrib = $this->contributionFor($slip);

            $gross += $contrib['gross'];
            $t1Base += $contrib['t1_base'];
            $t1Emp += $contrib['t1_emp'];
            $t1Pat += $contrib['t1_pat'];
            $t2Base += $contrib['t2_base'];
            $t2Emp += $contrib['t2_emp'];
            $t2Pat += $contrib['t2_pat'];
            $cssFamillePat += $contrib['css_famille_pat'];
        }

        return [
            'gross' => round($gross, 2),
            't1_base' => round($t1Base, 2),
            't1_emp' => round($t1Emp, 2),
            't1_pat' => round($t1Pat, 2),
            't2_base' => round($t2Base, 2),
            't2_emp' => round($t2Emp, 2),
            't2_pat' => round($t2Pat, 2),
            'css_famille_pat' => round($cssFamillePat, 2),
            'total_patronal' => round($t1Pat + $t2Pat + $cssFamillePat, 2),
            'slip_count' => $slips->count(),
        ];
    }

    /**
     * Calcul des cotisations IPRES/CSS pour un bulletin — source unique de
     * vérité des lignes ET des totaux (aucune dérive possible).
     *
     * @return array{gross: float, t1_base: float, t1_emp: float, t1_pat: float, t2_base: float, t2_emp: float, t2_pat: float, css_famille_pat: float, total_patronal: float}
     */
    private function contributionFor(PaySlip $slip): array
    {
        $gross = (float) $slip->gross_salary;
        $employee = $slip->employee;
        $isCadre = ($employee->ipres_category ?? 'general') === 'cadre';

        // Issue #2539 : taux/plafonds depuis les règles pays (source unique).
        $t1 = $this->contribution('IPRES_SN_EMP');
        $t1Pat = $this->contribution('IPRES_SN_PAT');
        $t2 = $this->contribution('IPRES_SN_EMP_T2');
        $t2Pat = $this->contribution('IPRES_SN_PAT_T2');
        $css = $this->contribution('CSS_SN_PAT_FAM');

        $t1Base = min($gross, $t1['cap'] ?? $gross);
        $t2Base = $isCadre
            ? max(0.0, min($gross, $t2['ceiling'] ?? $gross) - ($t2['floor'] ?? 0.0))
            : 0.0;

        $t1Emp = round($t1Base * $t1['rate'] / 100, 2);
        $t1PatAmt = round($t1Base * $t1Pat['rate'] / 100, 2);
        $t2Emp = round($t2Base * $t2['rate'] / 100, 2);
        $t2PatAmt = round($t2Base * $t2Pat['rate'] / 100, 2);
        $cssFamillePat = round(min($gross, $css['cap'] ?? $gross) * $css['rate'] / 100, 2);
        $totalPatronal = round($t1PatAmt + $t2PatAmt + $cssFamillePat, 2);

        return [
            'gross' => $gross,
            't1_base' => $t1Base,
            't1_emp' => $t1Emp,
            't1_pat' => $t1PatAmt,
            't2_base' => $t2Base,
            't2_emp' => $t2Emp,
            't2_pat' => $t2PatAmt,
            'css_famille_pat' => $cssFamillePat,
            'total_patronal' => $totalPatronal,
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
