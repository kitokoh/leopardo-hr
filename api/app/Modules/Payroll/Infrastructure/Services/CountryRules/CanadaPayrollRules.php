<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * Canada (CA): unlike CemacPayrollRules/CedeaoPayrollRules, Canada is a
 * single ISO 3166-1 alpha-2 country code, so countryCode() always returns
 * 'CA' regardless of province — the province is an *optional* refinement
 * (PA2-COUNTRY-009 acceptance criteria: "CAD province optionnelle timezone
 * placeholders overtime provinciaux"), not a separate persisted country
 * code. Provincial/territorial employment-standards legislation in Canada
 * differs mainly on: timezone (provinces span 6 IANA zones) and the
 * statutory weekly overtime threshold (federal Canada Labour Code default
 * is 44h/week; several provinces set a lower 40h/week threshold instead).
 * Minimum wage, social contributions (CPP/EI) and tax slabs are federal-
 * level placeholders here and intentionally do NOT vary per province in
 * this class — provincial minimum-wage/tax granularity is a separate,
 * larger scope not required by this ticket's acceptance criteria.
 */
class CanadaPayrollRules extends AbstractCountryRules
{
    /**
     * ISO 3166-2:CA province/territory subdivision codes this class
     * recognizes for forProvince()/the constructor. Passing null (or an
     * unrecognized code) keeps federal Canada Labour Code defaults, per
     * the "province optionnelle" acceptance criterion.
     */
    public const PROVINCE_CODES = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];

    protected ?string $province = null;

    public function __construct(?string $province = null)
    {
        $this->province = $this->normalizeProvince($province);
    }

    /**
     * Returns a clone scoped to a specific province/territory, so callers
     * that know the employee/company's province get its timezone and
     * statutory overtime threshold instead of the federal default. Pass
     * null to reset to the federal (no-province) default.
     */
    public function forProvince(?string $province): static
    {
        $clone = clone $this;
        $clone->province = $this->normalizeProvince($province);

        return $clone;
    }

    private function normalizeProvince(?string $province): ?string
    {
        if ($province === null || trim($province) === '') {
            return null;
        }

        $normalized = strtoupper(trim($province));

        return in_array($normalized, self::PROVINCE_CODES, true) ? $normalized : null;
    }

    public function countryCode(): string
    {
        return 'CA';
    }

    public function currency(): string
    {
        return 'CAD';
    }

    public function minimumWage(): float
    {
        // Federal reference placeholder only (does not vary per province
        // in this class): approximate monthly full-time equivalent of the
        // federal minimum wage (~CAD 17.30/hour x ~173.33 monthly hours).
        // Real provincial minimum wages differ (e.g. BC/ON are higher);
        // out of scope for this ticket's acceptance criteria.
        return 2999.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CPP/RPC Salariale', 'code' => 'CPP_CA_EMP', 'type' => 'employee', 'rate' => 5.95, 'cap' => null],
            ['name' => 'CPP/RPC Patronale', 'code' => 'CPP_CA_PAT', 'type' => 'employer', 'rate' => 5.95, 'cap' => null],
            ['name' => 'Assurance-emploi Salariale', 'code' => 'EI_CA_EMP', 'type' => 'employee', 'rate' => 1.66, 'cap' => null],
            ['name' => 'Assurance-emploi Patronale', 'code' => 'EI_CA_PAT', 'type' => 'employer', 'rate' => 2.32, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // Federal income tax brackets only (placeholder, approximate);
        // provincial income tax is layered on top in real Canadian payroll
        // and is intentionally not modeled here — out of scope for this
        // ticket's acceptance criteria.
        return [
            ['min' => 0, 'max' => 55867, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 55868, 'max' => 111733, 'rate' => 20.5, 'fixed_deduction' => 0],
            ['min' => 111734, 'max' => 173205, 'rate' => 26, 'fixed_deduction' => 0],
            ['min' => 173206, 'max' => 246752, 'rate' => 29, 'fixed_deduction' => 0],
            ['min' => 246753, 'max' => null, 'rate' => 33, 'fixed_deduction' => 0],
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
        return [
            'employee' => round($grossSalary * 0.0761, 2),
            'employer' => round($grossSalary * 0.0827, 2),
        ];
    }

    public function timezone(): string
    {
        // Provincial/territorial IANA timezone; falls back to
        // America/Toronto (Ontario, the most populous province) when no
        // province is set, matching the scope doc's "America/Toronto"
        // zone-wide default.
        return match ($this->province) {
            'BC' => 'America/Vancouver',
            'AB' => 'America/Edmonton',
            'SK' => 'America/Regina',
            'MB' => 'America/Winnipeg',
            'QC' => 'America/Toronto', // Eastern, same offset as Ontario
            'NB' => 'America/Moncton',
            'NS' => 'America/Halifax',
            'PE' => 'America/Halifax',
            'NL' => 'America/St_Johns',
            'YT' => 'America/Whitehorse',
            'NT' => 'America/Yellowknife',
            'NU' => 'America/Iqaluit',
            default => 'America/Toronto', // ON or no province set (federal default)
        };
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day used as the Canada-wide
        // default; provinces do not mandate a specific weekday.
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
        return 'Placeholder: no official Canadian public-holiday calendar is wired in yet, and statutory holidays '.
            'differ by province/territory; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'placeholder';
    }

    /**
     * PA2-COUNTRY-006 follow-up: matches App\Support\CountryDefaults,
     * where CA defaults to English.
     */
    public function language(): string
    {
        return 'en';
    }

    /**
     * PA2-COUNTRY-009: statutory weekly overtime threshold differs by
     * province — this is the provincial variation the acceptance criteria
     * ("overtime provinciaux") calls for. Falls back to the federal Canada
     * Labour Code threshold (44h/week) when no province is set. Placeholder-
     * grade sourcing (general employment-standards baselines, not locally
     * legally validated), see confidenceLevel().
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return match ($this->province) {
            'BC', 'MB', 'NL', 'QC', 'NT', 'NU', 'SK', 'YT' => 40.0,
            'NS', 'PE' => 48.0,
            'AB', 'NB', 'ON' => 44.0,
            default => 44.0, // federal Canada Labour Code default
        };
    }

    /**
     * PA2-COUNTRY-009: every supported province/territory (and the federal
     * default) uses a single +50% overtime premium tier beyond the
     * provincial threshold above; the provincial variation lives in the
     * threshold, not the multiplier. Placeholder-grade, see
     * confidenceLevel().
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
