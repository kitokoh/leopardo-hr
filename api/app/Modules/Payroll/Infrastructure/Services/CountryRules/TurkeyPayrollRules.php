<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class TurkeyPayrollRules extends AbstractCountryRules
{
    /**
     * Plafond mensuel SGK 2026 (tavan) : 9 909,00 TRY/jour × 30 =
     * 297 270,00 TRY/mois (Resmî Gazete 31/12/2025, SGK genelgesi).
     * S'applique à l'assiette de toutes les cotisations SGK + işsizlik.
     */
    private const SGK_TAVAN_MONTHLY = 297270.0;

    /**
     * Taux de la taxe de timbre (damga vergisi) sur les salaires :
     * binde 7,59 = 0,759 % (Damga Vergisi Kanunu — 71 Seri No.lu Tebliğ,
     * Resmî Gazete 31/12/2025). La part ≤ SMIC est exonérée depuis 2022
     * (art. 20, loi n° 7346 du 25/12/2022).
     */
    private const DAMGA_VERGISI_RATE = 0.759;

    public function countryCode(): string
    {
        return 'TR';
    }

    public function currency(): string
    {
        return 'TRY';
    }

    /**
     * Asgari ücret 2026 : 33 030,00 TRY bruts/mois (décision du Comité
     * Asgari Ücret Tespit Komisyonu publiée par le CSGB, effet 01/01/2026).
     * Net officiel : 28 075,50 TRY (cf. docs/payroll/TR_COMPLIANCE.md).
     */
    public function minimumWage(): float
    {
        return 33030.0;
    }

    public function socialContributions(): array
    {
        return [
            // SGK 2026 : sigortalı 14 % (9 % MYÖ + 5 % GSS), plafonné au tavan.
            ['name' => 'SGK Salariale', 'code' => 'SGK_TR_EMP', 'type' => 'employee', 'rate' => 14.0, 'cap' => self::SGK_TAVAN_MONTHLY],
            // SGK 2026 : işveren 21,75 % (12 % MYÖ + 7,5 % GSS + 2,25 % KVSK),
            // SANS incitation (teşvik) — cf. TR_COMPLIANCE.md §2 pour les
            // variantes 5 puan (16,75 %) / 2 puan (19,75 %).
            ['name' => 'SGK Patronale', 'code' => 'SGK_TR_PAT', 'type' => 'employer', 'rate' => 21.75, 'cap' => self::SGK_TAVAN_MONTHLY],
            ['name' => 'Chomage Salariale', 'code' => 'UNEMP_TR_EMP', 'type' => 'employee', 'rate' => 1.0, 'cap' => self::SGK_TAVAN_MONTHLY],
            ['name' => 'Chomage Patronale', 'code' => 'UNEMP_TR_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => self::SGK_TAVAN_MONTHLY],
        ];
    }

    /**
     * Barème gelir vergisi 2026 — tranches ANUELLES des salariés
     * (G.V.K. art. 103, tarife ücretliler, Resmî Gazete 31/12/2025) :
     * 190 000 (15 %) · 400 000 (20 %) · 1 500 000 (27 %) · 5 300 000
     * (35 %) · au-delà (40 %). L'assiette mensuelle est annualisée (× 12)
     * puis ramenée au mois (voir calculateIncomeTax).
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 190000, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 190001, 'max' => 400000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 400001, 'max' => 1500000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 1500001, 'max' => 5300000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 5300001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    /**
     * Gelir vergisi mensuel = progressif ANNUEL (assiette × 12) / 12, puis
     * soustraction de l'ASGARİ ÜCRET İSTİSNASI (exonération SMIC, loi
     * n° 7346 du 25/12/2022) : le salarié ne paie pas l'impôt correspondant
     * au SMIC net (28 075,50 TRY × 12 = 336 906 TRY/an en 2026).
     *
     * L'exonération vaut pour TOUS les salaires (pas seulement le SMIC) :
     * impôt dû = max(0, impôt mensuel − istisna mensuelle), chaque terme
     * arrondi au kuruş (pratique bordro TR : l'istisna est un montant
     * mensuel publié, pas une fraction de l'impôt annuel).
     */
    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());
        $monthlyTax = round($tax / $annualBasis, 2);

        // İstisna : impôt mensuel qui serait dû sur le SMIC NET
        // (33 030,00 − 14 % SGK − 1 % chômage = 28 075,50 TRY/mois).
        $minimumWageNetAnnual = $this->minimumWage() * (1 - 0.14 - 0.01) * $annualBasis;
        $monthlyExemptTax = round(
            $this->calculateProgressiveTax($minimumWageNetAnnual, $this->taxSlabs()) / $annualBasis,
            2
        );

        return round(max(0.0, $monthlyTax - $monthlyExemptTax), 2);
    }

    /**
     * Damga vergisi (taxe de timbre sur salaire) — binde 7,59 sur la part
     * du brut EXCÉDANT le SMIC (la part ≤ SMIC est exonérée depuis 2022,
     * loi n° 7346). Exposée via le mécanisme de taxe forfaitaire
     * (calculateBracketTax) pour trois raisons :
     *   1. la damga N'EST PAS déductible de l'assiette du gelir vergisi —
     *      la loger dans calculateSocialCharges fausserait l'assiette IR
     *      (moteur : taxable = brut − charges salarié) ;
     *   2. elle s'affiche ainsi comme une ligne de déduction dédiée
     *      « Damga vergisi (binde 7,59) » sur le bulletin ;
     *   3. la combinaison par défaut (additive, combineMinimumFiscalTax)
     *      l'ajoute au net sans toucher au noyau partagé.
     */
    public function stampTax(float $grossSalary): float
    {
        $excess = max(0.0, $grossSalary - $this->minimumWage());

        return round($excess * self::DAMGA_VERGISI_RATE / 100, 2);
    }

    /**
     * ZONE-INFRA (#1820) : la damga vergisi est portée par le mécanisme de
     * taxe forfaitaire (calculée sur le BRUT, assiette = part > SMIC).
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        return $this->stampTax($grossSalary);
    }

    /**
     * Libellé de la ligne forfaitaire TR (remplace « Taxe de minimum
     * fiscal ») : damga vergisi — binde 7,59 (0,759 %).
     */
    public function flatPayrollTaxLabel(): string
    {
        return 'Damga vergisi (binde 7,59)';
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // Assiette plafonnée au tavan SGK (297 270 TRY/mois en 2026).
        // La damga vergisi est volontairement EXCLUE des cotisations
        // salariales : elle n'est pas déductible de l'assiette IR (portée
        // par calculateBracketTax, cf. stampTax()).
        $sgkEmployee = $this->computeContribution($grossSalary, 'SGK_TR_EMP', 14.0, self::SGK_TAVAN_MONTHLY);
        $unempEmployee = $this->computeContribution($grossSalary, 'UNEMP_TR_EMP', 1.0, self::SGK_TAVAN_MONTHLY);
        $sgkEmployer = $this->computeContribution($grossSalary, 'SGK_TR_PAT', 21.75, self::SGK_TAVAN_MONTHLY);
        $unempEmployer = $this->computeContribution($grossSalary, 'UNEMP_TR_PAT', 2.0, self::SGK_TAVAN_MONTHLY);

        return [
            'employee' => round($sgkEmployee + $unempEmployee, 2),
            'employer' => round($sgkEmployer + $unempEmployer, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Europe/Istanbul';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Turkey.
        return [7];
    }

    /**
     * Monthly-only for now: not yet validated for daily/weekly pay cycles.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'TR fixed public holidays (Ulusal Bayram ve Genel Tatiller Kanunu, seed PublicHolidaySeeder, issue #2255): 1er jan, 23 avr, 1er mai, 19 mai, 15 juil, 30 août, 29 oct + mobiles islamiques (Ramazan Bayramı, Kurban Bayramı) — PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['TR'].
     */
    public function language(): string
    {
        return 'tr';
    }

    /**
     * PA2-COUNTRY-006: explicit compliance disclaimer required by the
     * ticket acceptance criteria ("seuils prudents et avertissement
     * conformite"). Overrides AbstractCountryRules::complianceWarning()
     * with wording specific to Turkish payroll law.
     */
    public function complianceWarning(): string
    {
        return 'Pilot ruleset for Turkiye: 2026 income tax slabs (GVK art. 103), '.
            'asgari ücret istisnası (minimum-wage tax exemption, Law 7346), SGK/unemployment '.
            'contribution rates (14 %/21,75 % + 1 %/2 %) and the 45h/week overtime tier are sourced from '.
            'official 2026 references (CSGB, SGK, Resmî Gazete) and are NOT a '.
            'substitute for a certified Turkish payroll provider or local mali '.
            'musavir. Do not rely on this for statutory payslip compliance '.
            'without validation.';
    }

    /**
     * PA2-COUNTRY-005 baseline: Turkish Labor Law No. 4857 art. 63 sets the
     * legal weekly working-hours threshold at 45 hours/week.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 45.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Labor Law No. 4857 art. 41 majore les heures
     * supplementaires de 50% du salaire horaire normal. Modelise ici comme
     * un palier unique, a titre pilote (confidenceLevel='pilot').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }
}
