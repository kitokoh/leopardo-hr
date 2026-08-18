<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * Règles de paie — Sénégal (SN) / XOF.
 *
 * Références légales (2026-08) :
 *   - CGI Sénégal (Code Général des Impôts) — art. 100 (abattement), 185 (TRIMF),
 *     150 (CFCE), 213 ss. (IR barème annuel)
 *   - Règlement IPRES — régime général T1 et régime cadres T2
 *   - CIPRES / CLEISS — CSS famille 7 %, plafond 63 000 XOF/mois
 *   - Code du travail sénégalais — art. 143 (HS), 65 ss. (préavis), 143 (congés)
 *
 * Statut : PRODUCTION — validé par analyse experte 2026-08-18 (#1912).
 * Voir docs/payroll/SN_VALIDATION.md pour la fiche détaillée.
 */
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
        // SMIG Sénégal — arrêté ministériel 2023 (dernier connu).
        return 58900.0;
    }

    /**
     * @return array<int, array{name: string, code: string, type: string, rate: float, cap: float|null, floor?: float, ceiling?: float, assiette_rate?: float}>
     */
    public function socialContributions(): array
    {
        return [
            // IPRES régime général T1 — plafonné à 432 000 XOF/mois.
            // Source : règlement IPRES (dernière révision validée, 2026-08-18 #1912).
            ['name' => 'IPRES Salariale', 'code' => 'IPRES_SN_EMP', 'type' => 'employee', 'rate' => 5.6, 'cap' => 432000.0],
            ['name' => 'IPRES Patronale', 'code' => 'IPRES_SN_PAT', 'type' => 'employer', 'rate' => 8.4, 'cap' => 432000.0],
            // IPRES régime cadres T2 — tranche 432 001 – 2 160 000 XOF.
            // Déclenché uniquement pour les employés de catégorie 'cadre'
            // (employees.ipres_category). Métadonnée floor/ceiling exposée pour
            // que la simulation par item soit cohérente avec le moteur.
            ['name' => 'IPRES Cadres Salariale (T2)', 'code' => 'IPRES_SN_EMP_T2', 'type' => 'employee', 'rate' => 2.4, 'cap' => null, 'floor' => 432000.0, 'ceiling' => 2160000.0],
            ['name' => 'IPRES Cadres Patronale (T2)', 'code' => 'IPRES_SN_PAT_T2', 'type' => 'employer', 'rate' => 3.6, 'cap' => null, 'floor' => 432000.0, 'ceiling' => 2160000.0],
            // CSS — prestations familiales 7 % (CIPRES/CLEISS officiel, #2473)
            // + AT 1 % (taux secteur bureau/services, configurable par branche),
            // chacune plafonnées à 63 000 XOF/mois.
            // NB : le plafond à 80 000 XOF annoncé en 2025 est contesté par le
            // CNP et non confirmé en vigueur → 63 000 maintenu (#1913).
            ['name' => 'CSS Prestations Familiales Patronale', 'code' => 'CSS_SN_PAT_FAM', 'type' => 'employer', 'rate' => 7.0, 'cap' => 63000.0],
            ['name' => 'CSS Accidents du Travail Patronale', 'code' => 'CSS_SN_PAT_AT', 'type' => 'employer', 'rate' => 1.0, 'cap' => 63000.0],
            // CFCE — Contribution Forfaitaire à la Charge de l'Employeur 3 %
            // sur la masse salariale brute, non plafonnée (CGI art. 150).
            ['name' => 'CFCE Patronale', 'code' => 'CFCE_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
        ];
    }

    /**
     * Barème IR annuel sénégalais (CGI Sénégal art. 213 et s., taux 2024).
     * 6 tranches progressives, validées par analyse expert 2026-08-18 (#1912).
     */
    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0,        'max' => 630000,   'rate' => 0,  'fixed_deduction' => 0],
            ['min' => 630001,   'max' => 1500000,  'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 1500001,  'max' => 4000000,  'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 4000001,  'max' => 8000000,  'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 8000001,  'max' => 13500000, 'rate' => 37, 'fixed_deduction' => 0],
            ['min' => 13500001, 'max' => null,     'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // Abattement frais professionnels 30 % du brut RÉEL, non plafonné
        // (CGI art. 100 ; SN_COMPLIANCE.md §1/§6).
        $grossForAbatement ??= $grossTaxable;
        $abatement = $this->professionalExpensesDeduction();
        $monthlyDeduction = $grossForAbatement * ($abatement['rate'] / 100);

        $annualTaxable = max(0.0, $grossTaxable - $monthlyDeduction) * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    /**
     * Calcul des charges sociales — brut seul (T2 déclenché par seuil brut,
     * comportement historique conservé pour rétro-compatibilité du moteur).
     *
     * Pour un calcul strict par catégorie d'employé, utiliser
     * {@see calculateSocialChargesWithCategory()}.
     */
    public function calculateSocialCharges(float $grossSalary): array
    {
        return $this->doCalculateSocialCharges($grossSalary, null);
    }

    /**
     * Calcul des charges sociales par catégorie IPRES (production).
     *
     * Issue #1912 (validation experte 2026-08-18) : l'IPRES T2 (régime cadres)
     * ne s'applique qu'aux employés de catégorie 'cadre'
     * (employees.ipres_category). L'ancienne approximation par seuil de brut
     * est remplacée ici par la vérification de catégorie.
     *
     * @param ?string $category  Valeur de employees.ipres_category :
     *                           'cadre' | 'general' | 'ouvrier' | null
     */
    public function calculateSocialChargesWithCategory(float $grossSalary, ?string $category): array
    {
        return $this->doCalculateSocialCharges($grossSalary, $category);
    }

    /**
     * Implémentation commune des charges sociales.
     *
     * Déclencheur IPRES T2 (issue #1912) :
     *   - Avec catégorie : T2 si $category === 'cadre' ET brut > 432 000.
     *   - Sans catégorie (null) : T2 si brut > 432 000 (approximation pilote
     *     conservée pour la compatibilité du moteur de base).
     *
     * @param ?string $category  employees.ipres_category ou null
     * @return array{employee: float, employer: float}
     */
    private function doCalculateSocialCharges(float $grossSalary, ?string $category): array
    {
        $ipresCap  = 432000.0;
        $cssCap    = 63000.0;
        $t2Floor   = 432000.0;
        $t2Ceiling = 2160000.0;

        $employee = $this->computeContribution($grossSalary, 'IPRES_SN_EMP', 5.6, $ipresCap);
        $employer = $this->computeContribution($grossSalary, 'IPRES_SN_PAT', 8.4, $ipresCap);

        // IPRES T2 — applicable aux cadres uniquement.
        $applyT2 = $grossSalary > $t2Floor && (
            $category === null                                     // mode historique
            || strtolower((string) $category) === 'cadre'
        );

        if ($applyT2) {
            $t2Base = min($grossSalary, $t2Ceiling) - $t2Floor;
            $employee += round($t2Base * $this->resolveContributionRate('IPRES_SN_EMP_T2', 2.4) / 100, 2);
            $employer += round($t2Base * $this->resolveContributionRate('IPRES_SN_PAT_T2', 3.6) / 100, 2);
        }

        $employer += $this->computeContribution($grossSalary, 'CSS_SN_PAT_FAM', 7.0, $cssCap)
            + $this->computeContribution($grossSalary, 'CSS_SN_PAT_AT', 1.0, $cssCap)
            + $this->computeContribution($grossSalary, 'CFCE_SN_PAT', 3.0, null);

        return [
            'employee' => round($employee, 2),
            'employer' => round($employer, 2),
        ];
    }

    /**
     * TRIMF — Taxe Représentative des Impôts du Minimum Fiscal.
     *
     * Taxe forfaitaire mensuelle par tranche de brut (CGI Sénégal art. 185).
     * 6 tranches validées par analyse experte 2026-08-18 (#1912).
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        return match (true) {
            $grossSalary <= 25000  => 900.0,
            $grossSalary <= 75000  => 2700.0,
            $grossSalary <= 150000 => 5400.0,
            $grossSalary <= 350000 => 9000.0,
            $grossSalary <= 700000 => 18000.0,
            default                => 36000.0,
        };
    }

    /**
     * Abattement frais professionnels — 30 % du brut, NON plafonné.
     *
     * Source : CGI Sénégal art. 100.
     * Validé par analyse experte 2026-08-18 (#1912).
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        return ['rate' => 30.0, 'cap' => null];
    }

    /**
     * Préavis sénégalais (Code du travail).
     *
     * Durée en JOURS OUVRÉS (#2219). Catégories :
     *   'cadre'            → 3 mois = 66 j ouvrés
     *   'ouvrier'/'worker' → 8 j calendaires = 6 j ouvrés
     *   autre / null       → 1 mois = 22 j ouvrés (employés/techniciens)
     *
     * Validé par analyse experte 2026-08-18 (#1912).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return match (strtolower((string) $category)) {
            'cadre'              => 66.0,
            'ouvrier', 'worker'  => 6.0,
            default              => 22.0,
        };
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
        return [7]; // dimanche
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
        return 'SN fixed public holidays (seed PublicHolidaySeeder, issue #2255): '
            . '1er jan, 4 avr, 1er mai, 15 août, 1er nov, 25 déc + mobiles islamiques '
            . '(Aïd el-Fitr, Aïd el-Adha, Maouloud) — PA2-COUNTRY-012.';
    }

    /**
     * Issue #1912 — validation experte 2026-08-18 :
     * IR (6 tranches), TRIMF, mécanisme max(IR/TRIMF), IPRES T1/T2, CSS
     * famille 7 % / AT 1 %, CFCE 3 %, abattement 30 %, préavis, HS —
     * tous vérifiés contre CGI Sénégal, IPRES, CIPRES/CLEISS, Code du travail.
     * Voir docs/payroll/SN_VALIDATION.md.
     */
    public function confidenceLevel(): string
    {
        return 'production';
    }

    /** Date de validation expert (#1912). */
    public function verificationDate(): string
    {
        return '2026-08-18';
    }

    /**
     * PA2-COUNTRY-006 — doit correspondre à CountryDefaults::DEFAULTS['SN'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * Seuil légal — 40 h/semaine (Code du travail, secteurs non agricoles).
     * Validé expert 2026-08-18 (#1912).
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * Paliers heures supplémentaires (Code du travail Sénégal art. 143) :
     *   +15 % les 8 premières heures supplémentaires/semaine
     *   +40 % au-delà ou de nuit
     * Validés expert 2026-08-18 (#1912).
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0,  'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.40],
        ];
    }

    /**
     * Le salarié paie le PLUS ÉLEVÉ de IR / TRIMF (CGI art. 185 — le TRIMF
     * est un minimum représentatif de l'impôt).
     * Validé expert 2026-08-18 (#1912).
     */
    public function combineMinimumFiscalTax(float $incomeTax, float $bracketTax): float
    {
        return max($incomeTax, $bracketTax);
    }
}
