<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;

/**
 * Issue #1830 — déclaration IPRES/CSS mensuelle Sénégal (CSV).
 *
 * Une ligne par bulletin validé : matricule IPRES employé, nom, prénom,
 * catégorie (general/cadre), brut, assiette T1 (min(brut, 432 000 XOF)),
 * cotisations T1 salariale (5,6 %) / T1 patronale (8,4 %), assiette T2
 * (cadres : min(brut, 2 160 000) − 432 000), cotisations T2 salariale
 * (2,4 %) / T2 patronale (3,6 %), CSS famille patronale (3 %).
 * Ligne TOTAUX en fin de fichier pour contrôle.
 *
 * Taux et plafonds résolus depuis les règles pays SN (SenegalPayrollRules)
 * — source de vérité unique alignée sur docs/payroll/SN_COMPLIANCE.md §4-6.
 * ⚠️ Format à valider avec un comptable IPRES sénégalais (structure interne
 * documentée, comme CnasDeclarationGenerator pour la DZ).
 */
class IpresDeclarationGenerator
{
    public const IPRES_T1_CAP = 432000.0;

    public const IPRES_T2_CEILING = 2160000.0;

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,ipres_matricule'])
            ->where('status', 'validated')
            ->get();

        $contributions = $this->contributions();

        $header = [
            'matricule_ipres',
            'nom',
            'prenom',
            'categorie',
            'salaire_brut',
            'assiette_t1',
            't1_salariale',
            't1_patronale',
            'assiette_t2',
            't2_salariale',
            't2_patronale',
            'css_famille_patronale',
        ];
        $rows = [$header];

        $totals = $this->emptyTotals();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $isCadre = $gross > self::IPRES_T1_CAP;
            $categorie = $isCadre ? 'cadre' : 'general';

            $t1Base = min($gross, self::IPRES_T1_CAP);
            $t1Salariale = $this->contribution($t1Base, $contributions, 'IPRES_SN_EMP');
            $t1Patronale = $this->contribution($t1Base, $contributions, 'IPRES_SN_PAT');

            $t2Base = $isCadre ? max(0.0, min($gross, self::IPRES_T2_CEILING) - self::IPRES_T1_CAP) : 0.0;
            $t2Salariale = $isCadre ? $this->contribution($t2Base, $contributions, 'IPRES_SN_EMP_T2') : 0.0;
            $t2Patronale = $isCadre ? $this->contribution($t2Base, $contributions, 'IPRES_SN_PAT_T2') : 0.0;

            $cssFamillePatronale = $this->contribution($gross, $contributions, 'CSS_SN_PAT_FAM');

            $totals['assiette_t1'] += $t1Base;
            $totals['t1_salariale'] += $t1Salariale;
            $totals['t1_patronale'] += $t1Patronale;
            $totals['assiette_t2'] += $t2Base;
            $totals['t2_salariale'] += $t2Salariale;
            $totals['t2_patronale'] += $t2Patronale;
            $totals['css_famille_patronale'] += $cssFamillePatronale;

            $rows[] = [
                (string) ($slip->employee->ipres_matricule ?? $slip->employee->matricule ?? $slip->employee_id),
                (string) ($slip->employee->last_name ?? ''),
                (string) ($slip->employee->first_name ?? ''),
                $categorie,
                number_format($gross, 2, '.', ''),
                number_format($t1Base, 2, '.', ''),
                number_format($t1Salariale, 2, '.', ''),
                number_format($t1Patronale, 2, '.', ''),
                number_format($t2Base, 2, '.', ''),
                number_format($t2Salariale, 2, '.', ''),
                number_format($t2Patronale, 2, '.', ''),
                number_format($cssFamillePatronale, 2, '.', ''),
            ];
        }

        $rows[] = [
            'TOTAUX',
            "{$slips->count()} bulletins",
            '',
            '',
            number_format($totals['assiette_t1'], 2, '.', ''),
            number_format($totals['assiette_t1'], 2, '.', ''),
            number_format($totals['t1_salariale'], 2, '.', ''),
            number_format($totals['t1_patronale'], 2, '.', ''),
            number_format($totals['assiette_t2'], 2, '.', ''),
            number_format($totals['t2_salariale'], 2, '.', ''),
            number_format($totals['t2_patronale'], 2, '.', ''),
            number_format($totals['css_famille_patronale'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Totaux de la déclaration (consommés par le contrôleur pour l'en-tête X-*).
     *
     * @return array{slips: int, assiette_t1: float, t1_salariale: float, t2_salariale: float, css_famille_patronale: float}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,ipres_matricule'])
            ->where('status', 'validated')
            ->get();

        $totals = $this->emptyTotals();
        $contributions = $this->contributions();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $isCadre = $gross > self::IPRES_T1_CAP;
            $t1Base = min($gross, self::IPRES_T1_CAP);
            $t2Base = $isCadre ? max(0.0, min($gross, self::IPRES_T2_CEILING) - self::IPRES_T1_CAP) : 0.0;

            $totals['assiette_t1'] += $t1Base;
            $totals['t1_salariale'] += $this->contribution($t1Base, $contributions, 'IPRES_SN_EMP');
            $totals['t2_salariale'] += $isCadre ? $this->contribution($t2Base, $contributions, 'IPRES_SN_EMP_T2') : 0.0;
            $totals['css_famille_patronale'] += $this->contribution($gross, $contributions, 'CSS_SN_PAT_FAM');
        }

        return [
            'slips' => $slips->count(),
            'assiette_t1' => round($totals['assiette_t1'], 2),
            't1_salariale' => round($totals['t1_salariale'], 2),
            't2_salariale' => round($totals['t2_salariale'], 2),
            'css_famille_patronale' => round($totals['css_famille_patronale'], 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function contributions(): array
    {
        $rules = new SenegalPayrollRules;

        return collect($rules->socialContributions())
            ->mapWithKeys(fn (array $c): array => [$c['code'] => (float) $c['rate']])
            ->all();
    }

    /**
     * @param  array<string, float>  $contributions
     */
    private function contribution(float $base, array $contributions, string $code): float
    {
        return round($base * ($contributions[$code] ?? 0.0) / 100, 2);
    }

    /**
     * @return array{assiette_t1: float, t1_salariale: float, t1_patronale: float, assiette_t2: float, t2_salariale: float, t2_patronale: float, css_famille_patronale: float}
     */
    private function emptyTotals(): array
    {
        return [
            'assiette_t1' => 0.0,
            't1_salariale' => 0.0,
            't1_patronale' => 0.0,
            'assiette_t2' => 0.0,
            't2_salariale' => 0.0,
            't2_patronale' => 0.0,
            'css_famille_patronale' => 0.0,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        // Champs systématiquement quotés (format déclaration stable pour le
        // dépôt RH) — échappement des guillemets internes.
        $lines = array_map(function (array $row): string {
            return '"'.implode('","', array_map(
                fn (string $field): string => str_replace('"', '""', $field),
                $row
            )).'"';
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
