<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class SenegalPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'SN';
    }

    public function currency(): string
    {
        return 'XOF';
    }

    public function minimumWage(): float
    {
        return 58900.0;
    }

    public function socialContributions(): array
    {
        return [
            // IPRES régime général T1 — plafonné à 432 000 XOF/mois.
            ['name' => 'IPRES Salariale', 'code' => 'IPRES_SN_EMP', 'type' => 'employee', 'rate' => 5.6, 'cap' => 432000.0],
            ['name' => 'IPRES Patronale', 'code' => 'IPRES_SN_PAT', 'type' => 'employer', 'rate' => 8.4, 'cap' => 432000.0],
            // IPRES régime cadres T2 — tranche 432 001 – 2 160 000 XOF
            // (issue #1827, docs/payroll/SN_COMPLIANCE.md §4bis).
            ['name' => 'IPRES Cadres Salariale (T2)', 'code' => 'IPRES_SN_EMP_T2', 'type' => 'employee', 'rate' => 2.4, 'cap' => null],
            ['name' => 'IPRES Cadres Patronale (T2)', 'code' => 'IPRES_SN_PAT_T2', 'type' => 'employer', 'rate' => 3.6, 'cap' => null],
            // CSS — prestations familiales 3 % (non plafonnées) + AT 1 % pilote.
            ['name' => 'CSS Prestations Familiales Patronale', 'code' => 'CSS_SN_PAT_FAM', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
            ['name' => 'CSS Accidents du Travail Patronale', 'code' => 'CSS_SN_PAT_AT', 'type' => 'employer', 'rate' => 1.0, 'cap' => null],
            // CFCE — Contribution Forfaitaire à la Charge de l'Employeur 3 %
            // (issue #1827, docs/payroll/SN_COMPLIANCE.md §5).
            ['name' => 'CFCE Patronale', 'code' => 'CFCE_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 630000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 630001, 'max' => 1500000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 1500001, 'max' => 4000000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 4000001, 'max' => 8000000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 8000001, 'max' => 13500000, 'rate' => 37, 'fixed_deduction' => 0],
            ['min' => 13500001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // Issue #1827 (docs/payroll/SN_COMPLIANCE.md §1/§6) : l'abattement frais
        // professionnels 30 % (non plafonné) s'applique sur le BRUT réel
        // ($grossForAbatement, passé par PayrollCalculator::calculateSlip()).
        // Sans lui, on retombe sur l'assiette passée (brut − cotisations) —
        // approximation pilot documentée. Suivi review #1847/#1828 : le moteur
        // ne l'appliquait pas du tout (IR sur-assiette, ex. SMIG 58 900 →
        // 620,32 d'IR au lieu de 0).
        $grossForAbatement ??= $grossTaxable;
        $abatement = $this->professionalExpensesDeduction();
        $monthlyDeduction = $grossForAbatement * ($abatement['rate'] / 100);

        $annualTaxable = max(0.0, $grossTaxable - $monthlyDeduction) * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // Issue #1827 (docs/payroll/SN_COMPLIANCE.md §4-§5) :
        //  - IPRES T1 5,6 % salarié / 8,4 % patronal plafonnés à 432 000 XOF ;
        //  - IPRES T2 (régime cadres) 2,4 % / 3,6 % sur la tranche
        //    432 001 – 2 160 000 XOF — appliquée quand le brut dépasse le
        //    plafond T1 (hypothèse pilote : brut > 432 k ⇒ régime cadres) ;
        //  - CSS prestations familiales 3 % + AT 1 % (non plafonnées) ;
        //  - CFCE 3 % sur la masse salariale brute (patronal uniquement).
        $ipresCap = 432000.0;
        $t2Floor = 432000.0;
        $t2Ceiling = 2160000.0;

        $employee = $this->computeContribution($grossSalary, 'IPRES_SN_EMP', 5.6, $ipresCap);
        $employer = $this->computeContribution($grossSalary, 'IPRES_SN_PAT', 8.4, $ipresCap);

        if ($grossSalary > $t2Floor) {
            $t2Base = min($grossSalary, $t2Ceiling) - $t2Floor;
            $employee += round($t2Base * $this->resolveContributionRate('IPRES_SN_EMP_T2', 2.4) / 100, 2);
            $employer += round($t2Base * $this->resolveContributionRate('IPRES_SN_PAT_T2', 3.6) / 100, 2);
        }

        $employer += $this->computeContribution($grossSalary, 'CSS_SN_PAT_FAM', 3.0, null)
            + $this->computeContribution($grossSalary, 'CSS_SN_PAT_AT', 1.0, null)
            + $this->computeContribution($grossSalary, 'CFCE_SN_PAT', 3.0, null);

        return [
            'employee' => round($employee, 2),
            'employer' => round($employer, 2),
        ];
    }

    /**
     * Issue #1827 : TRIMF — Taxe Représentative des Impôts du Minimum Fiscal
     * (CGI Sénégal) : taxe forfaitaire mensuelle retenue sur le salarié par
     * tranche de brut (docs/payroll/SN_COMPLIANCE.md §3). Portée dans le
     * bulletin par PayrollCalculator (ligne « Taxe de minimum fiscal »).
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        return match (true) {
            $grossSalary <= 25000 => 900.0,
            $grossSalary <= 75000 => 2700.0,
            $grossSalary <= 150000 => 5400.0,
            $grossSalary <= 350000 => 9000.0,
            $grossSalary <= 700000 => 18000.0,
            default => 36000.0,
        };
    }

    /**
     * Issue #1827 : abattement frais professionnels sénégalais — 30 % du
     * brut, NON plafonné (docs/payroll/SN_COMPLIANCE.md §6). Appliqué par
     * PayrollCalculator::calculateSlip() sur l'assiette imposable.
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        return ['rate' => 30.0, 'cap' => null];
    }

    /**
     * Issue #1827 : préavis sénégalais (Code du travail) — 8 jours ouvriers,
     * 1 mois employés/techniciens, 3 mois cadres. L'interface n'expose que
     * l'ancienneté : implémentation pilote au niveau employé/technicien
     * (30 jours) ; ouvriers/cadres documentés dans SN_COMPLIANCE.md §8, la
     * catégorie du contrat sera prise en compte dans un suivi.
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        return 30.0;
    }

    public function timezone(): string
    {
        return 'Africa/Dakar';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Senegal.
        return [7];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'placeholder: no official Senegalese public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    /**
     * Issue #1872 — références légales SN (docs/payroll/SN_COMPLIANCE.md).
     *
     * @return list<string>
     */
    public function legalSources(): array
    {
        return ['CGI Sénégal', 'IPRES', 'CSS', 'Code du travail'];
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['SN'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-005 baseline: Senegalese Code du travail sets the legal
     * weekly working-hours threshold at 40 hours/week for non-agricultural
     * sectors.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Code du travail senegalais majore les
     * heures supplementaires (15% pour les 8 premieres heures/semaine,
     * jusqu'a 40% au-dela ou de nuit). Modelise ici un palier a 2 niveaux, a
     * titre pilote (confidenceLevel='pilot').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.40],
        ];
    }

    /**
     * Issue #1934 — mécanisme légal SN : le salarié paie le PLUS ÉLEVÉ de
     * IR / TRIMF (le TRIMF est un minimum représentatif de l'impôt,
     * docs/payroll/SN_COMPLIANCE.md §3). Le moteur utilise cette combinaison
     * pour la base de déductions (computeNetBreakdown) et n'affiche que la
     * ligne gagnante sur le bulletin.
     */
    public function combineMinimumFiscalTax(float $incomeTax, float $bracketTax): float
    {
        return max($incomeTax, $bracketTax);
    }
}
