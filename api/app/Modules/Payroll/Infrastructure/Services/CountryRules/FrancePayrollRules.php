<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class FrancePayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'FR';
    }

    public function currency(): string
    {
        return 'EUR';
    }

    public function minimumWage(): float
    {
        // SMIC au 1er juin 2026 : 1 867,02 €/mois (12,31 €/h × 151,67 h),
        // revalorisation +2,41 % (décret 2026-05 ; +1,18 % au 1er janvier 2026).
        return 1867.02;
    }

    public function socialContributions(): array
    {
        // #5438 — structure URSSAF détaillée (pilot, gap E3 FR_COMPLIANCE.md).
        // PMSS 2026 = 4 005 €/mois (PASS 48 060 €, +2 %, arrêté 2026).
        $pmss = $this->pmss();

        return [
            ['name' => 'Maladie', 'code' => 'MAL_FR', 'type' => 'employer', 'rate' => 13.0, 'cap' => null],
            ['name' => 'Vieillesse plafonnee', 'code' => 'VIE_PLF_FR', 'type' => 'employee', 'rate' => 6.9, 'cap' => $pmss],
            ['name' => 'Vieillesse plafonnee', 'code' => 'VIE_PLF_PAT_FR', 'type' => 'employer', 'rate' => 8.55, 'cap' => $pmss],
            ['name' => 'Vieillesse deplaafonnee', 'code' => 'VIE_DPL_FR', 'type' => 'employee', 'rate' => 0.4, 'cap' => null],
            ['name' => 'Vieillesse deplaafonnee', 'code' => 'VIE_DPL_PAT_FR', 'type' => 'employer', 'rate' => 1.9, 'cap' => null],
            ['name' => 'Retraite complementaire T1', 'code' => 'RET_T1_FR', 'type' => 'employee', 'rate' => 3.15, 'cap' => $pmss],
            ['name' => 'Retraite complementaire T1', 'code' => 'RET_T1_PAT_FR', 'type' => 'employer', 'rate' => 4.72, 'cap' => $pmss],
            ['name' => 'Prevoyeance (pilot)', 'code' => 'PREV_FR', 'type' => 'employee', 'rate' => 1.5, 'cap' => null],
            ['name' => 'Prevoyeance (pilot)', 'code' => 'PREV_PAT_FR', 'type' => 'employer', 'rate' => 1.5, 'cap' => null],
            ['name' => 'Chomage', 'code' => 'CHO_FR', 'type' => 'employer', 'rate' => 4.05, 'cap' => null],
            ['name' => 'FNGS', 'code' => 'FNGS_FR', 'type' => 'employer', 'rate' => 0.5, 'cap' => null],
            // Issue #2220 : CSG/CRDS assises sur 98,25 % du brut (constante
            // légale, cf. calculateSocialCharges) — assiette_rate portée par
            // la métadonnée pour que la simulation par item = moteur.
            ['name' => 'CSG', 'code' => 'CSG_FR', 'type' => 'employee', 'rate' => 9.2, 'cap' => null, 'assiette_rate' => 98.25],
            ['name' => 'CRDS', 'code' => 'CRDS_FR', 'type' => 'employee', 'rate' => 0.5, 'cap' => null, 'assiette_rate' => 98.25],
        ];
    }

    /**
     * Plafond mensuel de la Sécurité sociale (PMSS) 2026.
     *
     * 4 005 €/mois (PASS 48 060 €/an, +2 % par rapport à 2025) — arrêté de
     * fin 2025, vérifié 2026-08-25 (source : service-public.fr / LégiSocial).
     */
    public function pmss(): float
    {
        return 4005.0;
    }

    protected function defaultTaxSlabs(): array
    {
        // Barème 2026 (revenus 2025, loi de finances 2026 — revalorisation
        // +0,9 %) : 0–11 600 € 0 % · 11 601–29 579 € 11 % · 29 580–84 577 € 30 %
        // · 84 578–181 917 € 41 % · > 181 917 € 45 %.
        return [
            ['min' => 0, 'max' => 11600, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 11601, 'max' => 29579, 'rate' => 11, 'fixed_deduction' => 0],
            ['min' => 29580, 'max' => 84577, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 84578, 'max' => 181917, 'rate' => 41, 'fixed_deduction' => 0],
            ['min' => 181918, 'max' => null, 'rate' => 45, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // #5438 — structure URSSAF détaillée (pilot, FR_COMPLIANCE.md §2).
        // Chaque ligne est arrondie à 2 décimales (computeContribution) puis
        // sommée : salarié = vieillesse plaf./déplaf. + retraite T1 + prévoyance
        // + CSG/CRDS (98,25 % du brut) ; employeur = maladie + vieillesse +
        // retraite T1 + prévoyance + chômage + FNGS. Plafonds PMSS honorés.
        $csgBase = $grossSalary * 0.9825;

        $employee = round(
            $this->computeContribution($grossSalary, 'VIE_PLF_FR', 6.9, $this->pmss())
            + $this->computeContribution($grossSalary, 'VIE_DPL_FR', 0.4, null)
            + $this->computeContribution($grossSalary, 'RET_T1_FR', 3.15, $this->pmss())
            + $this->computeContribution($grossSalary, 'PREV_PAT_FR', 1.5, null)
            + $csgBase * $this->resolveContributionRate('CSG_FR', 9.2) / 100
            + $csgBase * $this->resolveContributionRate('CRDS_FR', 0.5) / 100,
            2,
        );

        $employer = round(
            $this->computeContribution($grossSalary, 'MAL_FR', 13.0, null)
            + $this->computeContribution($grossSalary, 'VIE_PLF_PAT_FR', 8.55, $this->pmss())
            + $this->computeContribution($grossSalary, 'VIE_DPL_PAT_FR', 1.9, null)
            + $this->computeContribution($grossSalary, 'RET_T1_PAT_FR', 4.72, $this->pmss())
            + $this->computeContribution($grossSalary, 'PREV_FR', 1.5, null)
            + $this->computeContribution($grossSalary, 'CHO_FR', 4.05, null)
            + $this->computeContribution($grossSalary, 'FNGS_FR', 0.5, null),
            2,
        );

        return [
            'employee' => $employee,
            'employer' => $employer,
        ];
    }

    /**
     * Réduction générale des cotisations patronales (ex-Fillon) — pilot.
     *
     * Code du travail art. L241-13 (CSS) : coefficient =
     * (T / 0,6) × (1,6 × SMIC_annuel / rémunération_annuelle − 1),
     * borné entre 0 et T. T = 0,3206 (entreprises ≥ 20 salariés — pilot,
     * valeur 2026 à confirmer par expert-comptable). La réduction s'annule
     * au-delà de 1,6 × SMIC mensuel (2 987,23 € en 2026).
     *
     * @return float Montant mensuel de la réduction (0 si non éligible).
     */
    public function fillonReduction(float $monthlyGross): float
    {
        $t = 0.3206;
        $smicMonthly = $this->minimumWage();
        $annualGross = $monthlyGross * 12;
        $annualSmic = $smicMonthly * 12;

        $coefficient = ($t / 0.6) * ((1.6 * $annualSmic / max($annualGross, 0.01)) - 1);
        $coefficient = max(0.0, min($coefficient, $t));

        return round($coefficient * $monthlyGross, 2);
    }

    /**
     * Prélèvement à la source (PAS) — taux personnalisé (pilot, gap E2).
     *
     * Retenue mensuelle = assiette nette imposable × taux / 100. Le taux
     * neutre (par défaut) reste `calculateIncomeTax()` ; un taux personnalisé
     * transmis par l'administration s'applique ici.
     */
    public function withholdingTax(float $taxableBase, float $rate): float
    {
        return round($taxableBase * $rate / 100, 2);
    }

    /**
     * Net social (bulletin de paie, obligatoire depuis 2023) — définition
     * pilot : brut − cotisations salariales (CSG/CRDS comprises).
     */
    public function netSocial(float $grossSalary, float $employeeCharges): float
    {
        return round($grossSalary - $employeeCharges, 2);
    }

    public function timezone(): string
    {
        return 'Europe/Paris';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Saturday and Sunday are the standard weekly rest days in France.
        return [6, 7];
    }

    /**
     * Monthly-only for now: French payroll (bulletin de paie) is
     * structured around monthly cycles by convention; daily/weekly cycles
     * are not yet modeled for this country.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'FR fixed public holidays (Code du travail art. L3133-1, seed PublicHolidaySeeder, issue #2255): 1er jan, 1er mai, 8 mai, 14 juil, 15 août, 1er nov, 11 nov, 25 déc + mobiles (lundi de Pâques, Ascension, lundi de Pentecôte) — PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['FR'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-006: explicit compliance disclaimer required by the
     * ticket acceptance criteria ("seuils prudents et avertissement
     * conformite"). Overrides AbstractCountryRules::complianceWarning()
     * with wording specific to French payroll law, naming the concrete
     * areas (tax slabs, social contributions, overtime tiers) that are
     * pilot-sourced rather than validated against a payroll provider or
     * local counsel.
     */
    public function complianceWarning(): string
    {
        return 'Pilot ruleset for France: income tax slabs, social contribution '.
            'rates (securite sociale, CSG/CRDS) and the 35h/week overtime tiers '.
            'are sourced from general public references (Code du travail, '.
            'bareme fiscal indicatif) and are NOT a substitute for a certified '.
            'French payroll provider (DSN) or local expert-comptable. Do not '.
            'rely on this for statutory payslip compliance without validation.';
    }

    /**
     * PA2-COUNTRY-005 baseline: French Code du travail art. L3121-27 sets
     * the legal weekly working-hours threshold (duree legale) at 35
     * hours/week.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 35.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Code du travail art. L3121-36 majore les 8
     * premieres heures supplementaires (36e a 43e heure) de 25%, puis les
     * heures suivantes de 50%, sauf accord de branche/entreprise different.
     * A titre pilote (confidenceLevel='pilot'), la convention collective
     * applicable peut modifier ces taux.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.25],
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }
}
