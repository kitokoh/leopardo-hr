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
        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNPS_CEMAC_EMP', 'type' => 'employee', 'rate' => 4.2, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (pension/famille/AT)', 'code' => 'CNPS_CEMAC_PAT', 'type' => 'employer', 'rate' => 16.2, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
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
        $employeeRate = $this->resolveContributionRate('CNPS_CEMAC_EMP', 4.2);
        $employerRate = $this->resolveContributionRate('CNPS_CEMAC_PAT', 16.2);

        return [
            'employee' => round($grossSalary * $employeeRate / 100, 2),
            'employer' => round($grossSalary * $employerRate / 100, 2),
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
        return 'placeholder';
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
