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

        // Placeholder générique pour CM/CF/TD/GQ tant que leurs issues pays
        // n'ont pas livré de taux légaux validés (CM : #1821).
        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNPS_CEMAC_EMP', 'type' => 'employee', 'rate' => 4.2, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (pension/famille/AT)', 'code' => 'CNPS_CEMAC_PAT', 'type' => 'employer', 'rate' => 16.2, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
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

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
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

        // ZONE-INFRA (#1820): Cameroon (CM) statutory CNPS ceiling
        // 750 000 XAF/month is applied via computeContribution(); the other
        // CEMAC members stay on the placeholder (uncapped) rates until
        // their own member-state issues land (CM détaillé : #1821).
        $cap = $this->memberCountryCode === 'CM' ? 750000.0 : null;

        return [
            'employee' => $this->computeContribution($grossSalary, 'CNPS_CEMAC_EMP', 4.2, $cap),
            'employer' => $this->computeContribution($grossSalary, 'CNPS_CEMAC_PAT', 16.2, $cap),
        ];
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
        return 'placeholder: no official CEMAC member-state public-holiday calendar wired in yet; '.
            'national/religious holidays must be entered manually per company '.
            'until PA2-COUNTRY-012 delivers a real source.';
    }

    public function confidenceLevel(): string
    {
        // Gabon (GA) et Congo Brazzaville (CG) : IRPP/CNSS implémentés depuis
        // les sources publiques (DGI) — niveau 'pilot' (issue #1824) tant
        // qu'un expert-comptable local n'a pas validé les chiffres. CM/CF/TD/
        // GQ restent 'placeholder' jusqu'à leurs issues pays (CM : #1821).
        return in_array($this->memberCountryCode, ['GA', 'CG'], true) ? 'pilot' : 'placeholder';
    }

    /**
     * Préavis OHADA (issue #1824) — niveau employé/technicien (1 mois) :
     * ouvriers (8 jours) et cadres (3 mois) documentés dans
     * GA_COMPLIANCE.md §7 / CG_COMPLIANCE.md §7 — la catégorie du contrat
     * sera prise en compte dans un suivi.
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        if (in_array($this->memberCountryCode, ['GA', 'CG'], true)) {
            return 30.0;
        }

        return parent::noticePeriodDays($yearsOfService);
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
