<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * CEDEAO (#1830) — déclaration IPRES/CSS mensuelle Sénégal (CSV).
 *
 * Une ligne par bulletin validé : matricule IPRES, nom, prénom, catégorie
 * (general/cadre), brut, assiette T1 (min(brut, 432 000 XOF)), cotisation
 * T1 salariale (5,6 %) / patronale (8,4 %), assiette T2 (cadres
 * uniquement : min(brut, 2 160 000) − 432 000), cotisation T2 salariale
 * (2,4 %) / patronale (3,6 %), CSS famille patronale (3,0 %) + ligne
 * TOTAUX.
 *
 * ⚠️ Format interne documenté — à valider avec un comptable sénégalais.
 */
class IpresDeclarationGenerator
{
    public const IPRES_T1_CAP = 432000.0;

    public const IPRES_T2_CAP = 2160000.0;

    public const RATE_T1_EMP = 5.6;

    public const RATE_T1_PAT = 8.4;

    public const RATE_T2_EMP = 2.4;

    public const RATE_T2_PAT = 3.6;

    public const RATE_CSS_FAMILLE_PAT = 3.0;

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

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $employee = $slip->employee;

            /** @var string|null $matricule */
            $matricule = $employee->ipres_matricule ?? null;
            /** @var string|null $lastName */
            $lastName = $employee->last_name ?? null;
            /** @var string|null $firstName */
            $firstName = $employee->first_name ?? null;
            $isCadre = ($employee->ipres_category ?? 'general') === 'cadre';

            $t1Base = min($gross, self::IPRES_T1_CAP);
            $t2Base = $isCadre ? max(0.0, min($gross, self::IPRES_T2_CAP) - self::IPRES_T1_CAP) : 0.0;

            $t1Emp = round($t1Base * self::RATE_T1_EMP / 100, 2);
            $t1Pat = round($t1Base * self::RATE_T1_PAT / 100, 2);
            $t2Emp = round($t2Base * self::RATE_T2_EMP / 100, 2);
            $t2Pat = round($t2Base * self::RATE_T2_PAT / 100, 2);
            $cssFamillePat = round(min($gross, self::IPRES_T1_CAP) * self::RATE_CSS_FAMILLE_PAT / 100, 2);
            $totalPat = round($t1Pat + $t2Pat + $cssFamillePat, 2);

            $rows[] = [
                (string) ($matricule ?? ''),
                (string) ($lastName ?? ''),
                (string) ($firstName ?? ''),
                $isCadre ? 'cadre' : 'general',
                number_format($gross, 2, '.', ''),
                number_format($t1Base, 2, '.', ''),
                number_format($t1Emp, 2, '.', ''),
                number_format($t1Pat, 2, '.', ''),
                number_format($t2Base, 2, '.', ''),
                number_format($t2Emp, 2, '.', ''),
                number_format($t2Pat, 2, '.', ''),
                number_format($cssFamillePat, 2, '.', ''),
                number_format($totalPat, 2, '.', ''),
            ];
        }

        $totals = $this->totals($run);

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
            number_format($totals['total_patronal'], 2, '.', ''),
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
            $slipGross = (float) $slip->gross_salary;
            $isCadre = ($slip->employee->ipres_category ?? 'general') === 'cadre';

            $slipT1 = min($slipGross, self::IPRES_T1_CAP);
            $slipT2 = $isCadre ? max(0.0, min($slipGross, self::IPRES_T2_CAP) - self::IPRES_T1_CAP) : 0.0;

            $gross += $slipGross;
            $t1Base += $slipT1;
            $t1Emp += round($slipT1 * self::RATE_T1_EMP / 100, 2);
            $t1Pat += round($slipT1 * self::RATE_T1_PAT / 100, 2);
            $t2Base += $slipT2;
            $t2Emp += round($slipT2 * self::RATE_T2_EMP / 100, 2);
            $t2Pat += round($slipT2 * self::RATE_T2_PAT / 100, 2);
            $cssFamillePat += round($slipT1 * self::RATE_CSS_FAMILLE_PAT / 100, 2);
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
