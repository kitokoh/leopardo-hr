<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * CEMAC zone (Communaute Economique et Monetaire de l'Afrique Centrale):
 * Cameroon (CM), Central African Republic (CF), Chad (TD), Congo (CG),
 * Gabon (GA), Equatorial Guinea (GQ). All six members share the XAF
 * currency and a CNPS/CNSS-style social security scheme, so this single
 * class covers the whole zone's payroll logic with a per-member-state
 * sub-code instead of duplicating six near-identical classes
 * (PA2-COUNTRY-007).
 *
 * `country_code` columns (tax_slabs, social_contributions, payroll_runs...)
 * are `varchar(2)`, so the zone-wide label "CEMAC" is never persisted as a
 * country code: every usable instance is scoped to one of
 * MEMBER_COUNTRY_CODES via the constructor or forMemberCountry(), and
 * countryCode() returns that ISO 3166-1 alpha-2 code. Cameroon (CM) is the
 * representative default when no member state is specified, matching the
 * scope doc's "Africa/Douala" zone-wide default timezone.
 */
class CemacPayrollRules extends AbstractCountryRules
{
    /**
     * ISO 3166-1 alpha-2 codes of the CEMAC member states this class
     * supports via forMemberCountry()/the constructor.
     */
    public const MEMBER_COUNTRY_CODES = ['CM', 'CF', 'TD', 'CG', 'GA', 'GQ'];

    protected string $memberCountryCode;

    public function __construct(string $memberCountryCode = 'CM')
    {
        $normalized = strtoupper(trim($memberCountryCode));
        $this->memberCountryCode = in_array($normalized, self::MEMBER_COUNTRY_CODES, true) ? $normalized : 'CM';
    }

    /**
     * Returns a clone scoped to a specific CEMAC member state, so callers
     * that know the precise country (e.g. company provisioning) get the
     * member's timezone/minimum wage instead of the Cameroon default.
     */
    public function forMemberCountry(string $memberCountryCode): static
    {
        $clone = clone $this;
        $normalized = strtoupper(trim($memberCountryCode));
        $clone->memberCountryCode = in_array($normalized, self::MEMBER_COUNTRY_CODES, true) ? $normalized : $clone->memberCountryCode;

        return $clone;
    }

    public function countryCode(): string
    {
        return $this->memberCountryCode;
    }

    public function currency(): string
    {
        return 'XAF';
    }

    public function minimumWage(): float
    {
        // SMIG (Salaire Minimum Interprofessionnel Garanti) per member state,
        // most recent published figures.
        return match ($this->memberCountryCode) {
            'CF' => 35000.0,
            'TD' => 60000.0,
            'CG' => 90000.0,
            'GA' => 150000.0,
            'GQ' => 128000.0,
            default => 41875.0, // CM
        };
    }

    public function socialContributions(): array
    {
        // CM (#1821) : taux CNPS légaux (CGI 2024 / Code du travail 92/007) —
        // vieillesse 4,2 % salarié + 4,2 % patronal, famille 7,0 % patronal
        // (plafond 750 000 XAF/mois), AT 2,0 % patronal (non plafonné).
        if ($this->memberCountryCode === 'CM') {
            return [
                ['name' => 'CNPS Vieillesse Salariale (CM)', 'code' => 'CNPS_CM_VIE_EMP', 'type' => 'employee', 'rate' => 4.2, 'cap' => 750000.0],
                ['name' => 'CNPS Vieillesse Patronale (CM)', 'code' => 'CNPS_CM_VIE_PAT', 'type' => 'employer', 'rate' => 4.2, 'cap' => 750000.0],
                ['name' => 'CNPS Famille Patronale (CM)', 'code' => 'CNPS_CM_FAM_PAT', 'type' => 'employer', 'rate' => 7.0, 'cap' => 750000.0],
                ['name' => 'CNPS AT Patronale (CM)', 'code' => 'CNPS_CM_AT_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
            ];
        }
        if ($this->memberCountryCode === 'GA') {
            // CNSS Gabon (issue #1824) : retraite salarié 2,5 % + patronale
            // 5,0 % + famille 8,0 % plafonnés à 3 000 000 XAF/mois, AT 3,0 %
            // non plafonné (docs/payroll/GA_COMPLIANCE.md §3).
            return [
                ['name' => 'CNSS Retraite Salariale', 'code' => 'CNSS_GA_RET_EMP', 'type' => 'employee', 'rate' => 2.5, 'cap' => 3000000.0],
                ['name' => 'CNSS Retraite Patronale', 'code' => 'CNSS_GA_RET_PAT', 'type' => 'employer', 'rate' => 5.0, 'cap' => 3000000.0],
                ['name' => 'CNSS Prestations Familiales Patronale', 'code' => 'CNSS_GA_FAM_PAT', 'type' => 'employer', 'rate' => 8.0, 'cap' => 3000000.0],
                ['name' => 'CNSS Risques Professionnels Patronale', 'code' => 'CNSS_GA_AT_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
            ];
        }

        if ($this->memberCountryCode === 'CG') {
            // CNSS Congo Brazzaville (issue #1824) : retraite salarié 4,0 % +
            // patronale 8,0 % + famille 10,0 % plafonnés à 2 500 000 XAF/mois,
            // AT 3,0 % non plafonné (docs/payroll/CG_COMPLIANCE.md §3).
            return [
                ['name' => 'CNSS Retraite Salariale', 'code' => 'CNSS_CG_RET_EMP', 'type' => 'employee', 'rate' => 4.0, 'cap' => 2500000.0],
                ['name' => 'CNSS Retraite Patronale', 'code' => 'CNSS_CG_RET_PAT', 'type' => 'employer', 'rate' => 8.0, 'cap' => 2500000.0],
                ['name' => 'CNSS Prestations Familiales Patronale', 'code' => 'CNSS_CG_FAM_PAT', 'type' => 'employer', 'rate' => 10.0, 'cap' => 2500000.0],
                ['name' => 'CNSS Risques Professionnels Patronale', 'code' => 'CNSS_CG_AT_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
            ];
        }

        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNPS_CEMAC_EMP', 'type' => 'employee', 'rate' => 4.2, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (pension/famille/AT)', 'code' => 'CNPS_CEMAC_PAT', 'type' => 'employer', 'rate' => 16.2, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // CM (#1821) : IRPP Cameroun (CGI 2024, art. 68) — tranches ANNUELLES :
        //   0–2 000 000 XAF : 10 % · 2 000 001–3 000 000 : 15 %
        //   3 000 001–5 000 000 : 25 % · > 5 000 000 : 35 %
        // (valeurs à valider par expert-comptable — confidenceLevel 'pilot').
        if ($this->memberCountryCode === 'CM') {
            return [
                ['min' => 0, 'max' => 2000000, 'rate' => 10, 'fixed_deduction' => 0],
                ['min' => 2000001, 'max' => 3000000, 'rate' => 15, 'fixed_deduction' => 0],
                ['min' => 3000001, 'max' => 5000000, 'rate' => 25, 'fixed_deduction' => 0],
                ['min' => 5000001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
            ];
        }
        if ($this->memberCountryCode === 'GA') {
            // IRPP Gabon (DGI — issue #1824) — tranches ANNUELLES
            // (docs/payroll/GA_COMPLIANCE.md §1).
            return [
                ['min' => 0, 'max' => 1500000, 'rate' => 0, 'fixed_deduction' => 0],
                ['min' => 1500001, 'max' => 1920000, 'rate' => 5, 'fixed_deduction' => 0],
                ['min' => 1920001, 'max' => 2700000, 'rate' => 10, 'fixed_deduction' => 0],
                ['min' => 2700001, 'max' => 3600000, 'rate' => 15, 'fixed_deduction' => 0],
                ['min' => 3600001, 'max' => 5160000, 'rate' => 20, 'fixed_deduction' => 0],
                ['min' => 5160001, 'max' => 7500000, 'rate' => 25, 'fixed_deduction' => 0],
                ['min' => 7500001, 'max' => 11000000, 'rate' => 30, 'fixed_deduction' => 0],
                ['min' => 11000001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
            ];
        }

        if ($this->memberCountryCode === 'CG') {
            // IRPP Congo Brazzaville (DGI — issue #1824) — tranches ANNUELLES
            // (docs/payroll/CG_COMPLIANCE.md §1).
            return [
                ['min' => 0, 'max' => 464000, 'rate' => 0, 'fixed_deduction' => 0],
                ['min' => 464001, 'max' => 1000000, 'rate' => 1, 'fixed_deduction' => 0],
                ['min' => 1000001, 'max' => 3000000, 'rate' => 10, 'fixed_deduction' => 0],
                ['min' => 3000001, 'max' => 8000000, 'rate' => 25, 'fixed_deduction' => 0],
                ['min' => 8000001, 'max' => 13000000, 'rate' => 40, 'fixed_deduction' => 0],
                ['min' => 13000001, 'max' => null, 'rate' => 45, 'fixed_deduction' => 0],
            ];
        }

        // Conservative placeholder progressive IRPP-style schedule, common
        // shape across CEMAC members. confidenceLevel() below explicitly
        // marks this as 'placeholder', not a legally validated figure per
        // member state.
        return [
            ['min' => 0, 'max' => 500000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 500001, 'max' => 1000000, 'rate' => 10, 'fixed_deduction' => 0],
            ['min' => 1000001, 'max' => 2500000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 2500001, 'max' => 5000000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 5000001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        if ($this->memberCountryCode === 'GA') {
            // IRPP Gabon (DGI — issue #1824/#1939/#2118) : barème ANNUEL
            // (8 tranches, GA_COMPLIANCE.md §1) appliqué APRÈS l'abattement
            // frais professionnels DGI — 20 % si la base imposable annuelle
            // < 4 166 666 XAF, sinon 833 333 XAF fixe (méthode dédiée
            // gabonProfessionalExpensesAbatement(), jamais de calcul inline —
            // constitution §III).
            $annualTaxable = $grossTaxable * $annualBasis;
            $abatement = $this->gabonProfessionalExpensesAbatement($annualTaxable);
            $annualTaxable = max(0.0, $annualTaxable - $abatement);
            $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

            return round($tax / $annualBasis, 2);
        }

        if ($this->memberCountryCode !== 'CM') {
            $annualTaxable = $grossTaxable * $annualBasis;
            $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

            return round($tax / $annualBasis, 2);
        }

        // CM (#1821, CGI 2024 art. 68) :
        //   1. Assiette = brut − CNPS salariale − abattement frais pro
        //      (30 % du BRUT, plafonné 350 000 XAF/mois — formule légale).
        //      Le moteur passe $grossTaxable = brut − CNPS salariale et
        //      $grossForAbatement = brut réel ; l'abattement s'applique sur
        //      le brut réel (défaut : $grossTaxable si non fourni).
        //   2. IRPP progressif annuel / 12 (tranches art. 68).
        //   3. Centimes additionnels (10 % de l'IRPP) : IRPP × 1,10.
        $abatement = $this->professionalExpensesDeduction();
        $abatementBase = $grossForAbatement ?? $grossTaxable;
        $monthlyDeduction = min(
            $abatementBase * ($abatement['rate'] / 100),
            $abatement['cap'] ?? PHP_FLOAT_MAX
        );

        $annualTaxable = max(0.0, $grossTaxable - $monthlyDeduction) * $annualBasis;
        $monthlyTax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs()) / $annualBasis;

        return round($monthlyTax * 1.10, 2);
    }

    /**
     * CM (#1821) : abattement frais professionnels 30 % du brut plafonné
     * 350 000 XAF/mois (CGI 2024 — 4 200 000 XAF/an). Les autres membres
     * CEMAC n'en ont pas (défaut 0 %). Le Gabon (GA) déroge : son abattement
     * DGI est annuel et conditionnel — voir
     * gabonProfessionalExpensesAbatement() (issue #2118).
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        if ($this->memberCountryCode === 'CM') {
            return ['rate' => 30.0, 'cap' => 350000.0];
        }

        return parent::professionalExpensesDeduction();
    }

    /**
     * GA (#2118) — abattement frais professionnels DGI (docs/payroll/
     * GA_COMPLIANCE.md §1) : 20 % de la base imposable ANNUELLE si elle est
     * inférieure à 4 166 666 XAF, sinon abattement FIXE de 833 333 XAF,
     * appliqué AVANT le barème IRPP annuel. Méthode dédiée (constitution
     * §III : jamais de calcul inline dans calculateIncomeTax()).
     *
     * Source : DGI Gabon (CGI art. 174 — à confirmer par l'expert-comptable
     * local, registre VALIDATION_EXPERTE.md #1904).
     */
    public function gabonProfessionalExpensesAbatement(float $annualTaxableBase): float
    {
        return $annualTaxableBase < 4166666.0
            ? $annualTaxableBase * 0.20
            : 833333.0;
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // CM (#1821) : CNPS avec plafond statutaire 750 000 XAF/mois sur la
        // vieillesse et la famille ; l'AT (2 %) n'est pas plafonné. Les autres
        // cinq membres CEMAC restent sur le placeholder (non plafonné) jusqu'à
        // leurs propres issues (GA/CG : #1824).
        if ($this->memberCountryCode === 'CM') {
            $cap = 750000.0;

            return [
                'employee' => $this->computeContribution($grossSalary, 'CNPS_CM_VIE_EMP', 4.2, $cap),
                'employer' => $this->computeContribution($grossSalary, 'CNPS_CM_VIE_PAT', 4.2, $cap)
                    + $this->computeContribution($grossSalary, 'CNPS_CM_FAM_PAT', 7.0, $cap)
                    + $this->computeContribution($grossSalary, 'CNPS_CM_AT_PAT', 2.0, null),
            ];
        }
        if ($this->memberCountryCode === 'GA') {
            // CNSS Gabon (issue #1824) : retraite 2,5 % salarié et 5,0 %
            // patronal + famille 8,0 % patronal plafonnés à 3 000 000
            // XAF/mois, AT 3,0 % non plafonné (docs/payroll/GA_COMPLIANCE.md §3).
            return [
                'employee' => $this->computeContribution($grossSalary, 'CNSS_GA_RET_EMP', 2.5, 3000000.0),
                'employer' => round(
                    $this->computeContribution($grossSalary, 'CNSS_GA_RET_PAT', 5.0, 3000000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_GA_FAM_PAT', 8.0, 3000000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_GA_AT_PAT', 3.0, null),
                    2
                ),
            ];
        }

        if ($this->memberCountryCode === 'CG') {
            // CNSS Congo Brazzaville (issue #1824) : retraite 4,0 % salarié et
            // 8,0 % patronal + famille 10,0 % patronal plafonnés à 2 500 000
            // XAF/mois, AT 3,0 % non plafonné (docs/payroll/CG_COMPLIANCE.md §3).
            return [
                'employee' => $this->computeContribution($grossSalary, 'CNSS_CG_RET_EMP', 4.0, 2500000.0),
                'employer' => round(
                    $this->computeContribution($grossSalary, 'CNSS_CG_RET_PAT', 8.0, 2500000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_CG_FAM_PAT', 10.0, 2500000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_CG_AT_PAT', 3.0, null),
                    2
                ),
            ];
        }

        // Placeholder ZONE-INFRA (#1820): the other CEMAC members (CF/TD/GQ)
        // stay on the placeholder (uncapped) rates until their own
        // member-state issues land. `$cap` est volontairement `null` ici :
        // il n'est défini que dans le bloc CM (fix review #1824 — la
        // référence à `$cap` indéfini cassait PHPStan strict + les tests
        // PHPUnit (failOnWarning) pour CF/TD/GQ).
        $cap = null;

        return [
            'employee' => $this->computeContribution($grossSalary, 'CNPS_CEMAC_EMP', 4.2, $cap),
            'employer' => $this->computeContribution($grossSalary, 'CNPS_CEMAC_PAT', 16.2, $cap),
        ];
    }

    /**
     * CM (#1821) : préavis légal (Code du travail 92/007, art. 34) —
     *   < 6 mois : 15 jours · 6 mois–5 ans : 1 mois (30 j)
     *   5–10 ans : 2 mois (60 j) · > 10 ans : 3 mois (90 j).
     * Les autres membres CEMAC gardent le défaut (0).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        if (in_array($this->memberCountryCode, ['GA', 'CG'], true)) {
            // Préavis OHADA (issue #1824) — niveau employé/technicien (1 mois) :
            // ouvriers (8 jours) et cadres (3 mois) documentés dans
            // GA_COMPLIANCE.md §7 / CG_COMPLIANCE.md §7 — la catégorie du
            // contrat sera prise en compte dans un suivi.
            // Issue #2219 : JOURS OUVRÉS (1 mois = 22 j ouvrés) — le moteur
            // divise par le nombre de jours ouvrés du mois (22) ; renvoyer
            // 30 (calendaires) surpaierait 30/22 = 1,36×.
            return 22.0;
        }

        if ($this->memberCountryCode !== 'CM') {
            return parent::noticePeriodDays($yearsOfService);
        }

        // Issue #2219 : JOURS OUVRÉS — préavis CM (art. 34 loi 92/007) :
        // < 6 mois : 15 j calendaires = 11 j ouvrés · < 5 ans : 1 mois = 22 ·
        // < 10 ans : 2 mois = 44 · ≥ 10 ans : 3 mois = 66.
        return match (true) {
            $yearsOfService < 0.5 => 11.0,
            $yearsOfService < 5.0 => 22.0,
            $yearsOfService < 10.0 => 44.0,
            default => 66.0,
        };
    }

    public function timezone(): string
    {
        // All CEMAC members observe UTC+1 year-round (WAT).
        return match ($this->memberCountryCode) {
            'CF' => 'Africa/Bangui',
            'TD' => 'Africa/Ndjamena',
            'CG' => 'Africa/Brazzaville',
            'GA' => 'Africa/Libreville',
            'GQ' => 'Africa/Malabo',
            default => 'Africa/Douala', // CM
        };
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day across all CEMAC members.
        return [7];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        if ($this->memberCountryCode === 'CM') {
            return 'CM fixed public holidays: 1er jan, 11 fév, 1er mai, 20 mai, 15 août, 25 déc (CM_COMPLIANCE.md §6) + mobile Islamic holidays (Eid etc.) — Islamic calendar wiring pending.';
        }

        if ($this->memberCountryCode === 'GA') {
            return 'GA fixed public holidays (seed PublicHolidaySeeder, issue #2255): 1er jan, 12 mars, 17 avr, 1er mai, 15 août, 17 août, 1er nov, 25 déc + mobiles (Pâques, Aïds) — PA2-COUNTRY-012.';
        }

        if ($this->memberCountryCode === 'CG') {
            return 'CG fixed public holidays (seed PublicHolidaySeeder, issue #2255): 1er jan, 15 mars, 1er mai, 10 juin, 15 août, 1er nov, 25 déc + mobiles (Pâques, Aïds) — PA2-COUNTRY-012.';
        }

        return 'placeholder: no official CEMAC member-state public-holiday calendar wired in yet; '.
            'national/religious holidays must be entered manually per company '.
            'until PA2-COUNTRY-012 delivers a real source.';
    }

    public function confidenceLevel(): string
    {
        // CM (#1821), GA et CG (#1824) passent en 'pilot' : barèmes IRPP/CNSS
        // implémentés à partir de sources publiques, à valider par un
        // expert-comptable local avant passage en 'production'.
        return in_array($this->memberCountryCode, ['CM', 'GA', 'CG'], true) ? 'pilot' : 'placeholder';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults for all six
     * CEMAC member codes (CM/CF/TD/CG/GA/GQ), all French-speaking.
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-007 follow-up (BUGFIX-CEMAC-001): CountryRulesInterface
     * requires overtimeThresholdWeeklyHours()/overtimeRateTiers() (added by
     * PA2-COUNTRY-004) for every implementation, but CemacPayrollRules never
     * implemented them, causing a PHP fatal error (abstract methods not
     * implemented) on any code path that loads this class. Most CEMAC member
     * states' labor codes (Code du travail, largely harmonized across the
     * zone) set the standard legal weekly working-hours threshold at 40
     * hours/week, consistent with the other Francophone-derived country
     * rules already implemented here (e.g. Algeria, Senegal).
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * BUGFIX-CEMAC-001: conservative placeholder premium tiers (not yet
     * legally validated per member state, hence confidenceLevel() =
     * 'placeholder' rather than 'pilot'): +20% for the first 8 overtime
     * hours/week, +30% beyond, a common shape across CEMAC/OHADA labor
     * codes. Must be confirmed per member state before real payroll use.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.20],
            ['up_to_hours' => null, 'multiplier' => 1.30],
        ];
    }
}
