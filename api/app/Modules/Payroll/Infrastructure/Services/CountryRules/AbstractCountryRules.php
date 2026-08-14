<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

abstract class AbstractCountryRules implements CountryRulesInterface
{
    /**
     * Optional tenant scope used to look up company-specific tax slab overrides.
     * Set via forCompany(); left null means "use global/default slabs only".
     */
    protected ?string $companyId = null;

    /**
     * Point in time used to resolve which TaxSlab/SocialContribution rows are
     * "effective" (PA2-ARCH-004: country rates/tables are associated with an
     * effective date so a past payroll run can be recalculated for audit
     * purposes using the rates that applied *during its own period*, not
     * today's rates). Set via asOf(); null means "use now()", matching the
     * pre-existing behaviour.
     */
    protected ?Carbon $asOfDate = null;

    /**
     * Cache of resolved SocialContribution rows for the current asOfDate/
     * companyId scope, keyed by contribution code, so a single calculateRun()
     * only queries once per code even though calculateSocialCharges() may
     * read several rates from it.
     *
     * @var array<string, SocialContribution|null>
     */
    private array $resolvedContributions = [];

    /**
     * Returns a clone of this rules object scoped to a given company, so that
     * company-specific TaxSlab overrides (configured via TaxSlabController) are
     * taken into account by taxSlabs()/calculateIncomeTax().
     */
    public function forCompany(?string $companyId): static
    {
        $clone = clone $this;
        $clone->companyId = $companyId;

        return $clone;
    }

    /**
     * Returns a clone of this rules object scoped to a specific point in
     * time, so taxSlabs()/calculateIncomeTax()/calculateSocialCharges()
     * resolve the TaxSlab/SocialContribution rows that were effective on
     * that date instead of today's (PA2-ARCH-004). Typically called with a
     * payroll run's period date so recalculating an old run for audit stays
     * consistent with the rates that applied back then. Pass null to reset
     * to "use now()".
     */
    public function asOf(\DateTimeInterface|string|null $date): static
    {
        $clone = clone $this;
        $clone->asOfDate = $date === null ? null : Carbon::parse($date);
        $clone->resolvedContributions = [];

        return $clone;
    }

    /**
     * Effective tax slabs: company-specific override from the `tax_slabs` table
     * if present, else a global (company_id IS NULL) override from the same
     * table, else the country's hardcoded default slabs. This makes the
     * TaxSlabController CRUD API (and the underlying `tax_slabs` table) actually
     * affect payroll calculations, instead of being a disconnected admin screen.
     *
     * S-3 (#1663, D-01) : les barèmes « par défaut » sont NATIONAUX (périmètre
     * pays résolu par la règle, ex. DZ) — tout barème spécifique entreprise
     * doit être déclaré en base (`tax_slabs`) pour être pris en compte.
     *
     * Wrapped defensively: if called outside a booted Laravel app (e.g. pure
     * PHPUnit\Framework\TestCase unit tests with no facade root/DB), silently
     * falls back to defaultTaxSlabs() so existing unit tests keep passing
     * unchanged.
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    public function taxSlabs(): array
    {
        return $this->resolveTaxSlabsFromDatabase() ?? $this->defaultTaxSlabs();
    }

    /**
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>|null
     */
    protected function resolveTaxSlabsFromDatabase(): ?array
    {
        try {
            if (! Schema::hasTable('tax_slabs')) {
                return null;
            }

            $base = TaxSlab::query()->forCountry($this->countryCode())->effective($this->asOfDate);

            if ($this->companyId !== null) {
                $companySlabs = (clone $base)->where('company_id', $this->companyId)->orderBy('min_amount')->get();
                if ($companySlabs->isNotEmpty()) {
                    return $this->mapSlabs($companySlabs);
                }
            }

            $globalSlabs = (clone $base)->whereNull('company_id')->orderBy('min_amount')->get();
            if ($globalSlabs->isNotEmpty()) {
                return $this->mapSlabs($globalSlabs);
            }

            return null;
        } catch (\Throwable) {
            // No booted app/DB (e.g. pure unit tests) or transient DB error:
            // fall back to the hardcoded default slabs rather than fatal.
            return null;
        }
    }

    /**
     * @param  Collection<int, TaxSlab>  $slabs
     * @return array<int, array{min: float, max: float|null, rate: float, fixed_deduction: float}>
     */
    private function mapSlabs(Collection $slabs): array
    {
        return $slabs->map(static fn (TaxSlab $slab): array => [
            'min' => (float) $slab->min_amount,
            'max' => $slab->max_amount === null ? null : (float) $slab->max_amount,
            'rate' => (float) $slab->rate,
            'fixed_deduction' => (float) $slab->fixed_deduction,
        ])->all();
    }

    /**
     * Country-specific hardcoded fallback slabs, used only when no matching
     * rows exist in the `tax_slabs` table (fresh install before seeding, or
     * country not yet seeded).
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    abstract protected function defaultTaxSlabs(): array;

    /**
     * Resolves the effective rate (percentage points, e.g. 9.0 for 9%) for a
     * given SocialContribution code as of asOfDate() (or now() when unset),
     * scoped to companyId() with a fallback to the global (company_id IS
     * NULL) row. Falls back to $defaultRate when the `social_contributions`
     * table doesn't exist yet, no matching row is effective, or the app/DB
     * isn't booted (pure unit tests) — so existing hardcoded percentages
     * keep working unchanged until a country is actually seeded.
     *
     * This is what makes socialContributions()/the `social_contributions`
     * table (and its effective_from/effective_to columns) actually drive
     * calculateSocialCharges(), instead of being a disconnected admin CRUD
     * screen — the same disconnect PA2-ARCH-001 fixed for tax_slabs. It's
     * also what makes retroactive recalculation possible for audit purposes
     * (PA2-ARCH-004): recalculating an old payroll run with asOf() set to its
     * own period resolves the rate that was effective back then, not today's.
     */
    protected function resolveContributionRate(string $code, float $defaultRate): float
    {
        $contribution = $this->resolveContribution($code);

        return $contribution === null ? $defaultRate : $contribution->rate;
    }

    /**
     * Resolves the effective cap (same scoping rules as
     * resolveContributionRate() above) for a given SocialContribution code.
     * Returns $defaultCap when no matching DB row is found/effective.
     */
    protected function resolveContributionCap(string $code, ?float $defaultCap): ?float
    {
        $contribution = $this->resolveContribution($code);

        return $contribution === null ? $defaultCap : $contribution->cap;
    }

    private function resolveContribution(string $code): ?SocialContribution
    {
        if (array_key_exists($code, $this->resolvedContributions)) {
            return $this->resolvedContributions[$code];
        }

        try {
            if (! Schema::hasTable('social_contributions')) {
                return $this->resolvedContributions[$code] = null;
            }

            $base = SocialContribution::query()
                ->forCountry($this->countryCode())
                ->where('code', $code)
                ->effective($this->asOfDate);

            if ($this->companyId !== null) {
                $companyRow = (clone $base)->where('company_id', $this->companyId)->first();
                if ($companyRow !== null) {
                    return $this->resolvedContributions[$code] = $companyRow;
                }
            }

            $globalRow = (clone $base)->whereNull('company_id')->first();

            return $this->resolvedContributions[$code] = $globalRow;
        } catch (\Throwable) {
            // No booted app/DB (e.g. pure unit tests) or transient DB error:
            // fall back to the hardcoded default rate/cap rather than fatal.
            return $this->resolvedContributions[$code] = null;
        }
    }

    /**
     * PA2-COUNTRY-006: default compliance disclaimer shared by every
     * country implementation, derived from confidenceLevel() so it stays
     * accurate automatically as a country's rules mature from
     * 'placeholder'/'pilot' to 'production' without touching every class.
     * Subclasses may override with country-specific wording (e.g. citing a
     * specific labor code article) if that becomes useful later.
     */
    public function complianceWarning(): string
    {
        return match ($this->confidenceLevel()) {
            'production' => sprintf(
                'Legally validated for %s payroll use, but always confirm current rates with local counsel before relying on this for statutory filings.',
                $this->countryCode()
            ),
            'placeholder' => sprintf(
                'Structure-only placeholder for %s: tax/social-contribution figures are not yet researched and must not be used for real payroll runs without replacing them first.',
                $this->countryCode()
            ),
            default => sprintf(
                'Pilot ruleset for %s, sourced from general public labor-code references but not yet legally validated locally. Confirm with local legal/tax counsel before relying on these figures (tax slabs, social contributions, overtime thresholds) for statutory compliance.',
                $this->countryCode()
            ),
        };
    }

    /**
     * Calculates progressive tax for slabs declared with inclusive human-readable
     * bounds such as 0-5000, 5001-20000.
     *
     * @param  array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>  $slabs
     */
    protected function calculateProgressiveTax(float $taxableAmount, array $slabs): float
    {
        $tax = 0.0;

        foreach ($slabs as $slab) {
            $lowerBound = (float) $slab['min'];
            if ($lowerBound > 0) {
                $lowerBound -= 1;
            }

            $upperBound = $slab['max'] === null ? PHP_FLOAT_MAX : (float) $slab['max'];
            if ($taxableAmount <= $lowerBound) {
                continue;
            }

            $taxableInSlab = min($taxableAmount, $upperBound) - $lowerBound;
            if ($taxableInSlab <= 0) {
                continue;
            }

            $tax += $taxableInSlab * ((float) $slab['rate'] / 100);

            if ($taxableAmount <= $upperBound) {
                break;
            }
        }

        return $tax;
    }

    /**
     * Historic default: no statutory notice pay (the contract/execution
     * decides). Countries with a documented legal notice period override
     * this. FOCUS 2 (F-31).
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        return 0.0;
    }

    /**
     * Historic default: one month of base salary per year of service
     * (F-08). Countries may override with tiered legal formulas. FOCUS 2
     * (F-31).
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 1.0;
    }

    /**
     * ZONE-INFRA (#1820): single helper to compute a social contribution
     * with its statutory cap applied, used by every country's
     * calculateSocialCharges() so the cap logic (base = min(gross, cap))
     * lives in exactly one place instead of being re-implemented per
     * country (which is how caps get forgotten and bugs ship to
     * production). The rate and cap are resolved from the
     * `social_contributions` table when present (effective dating,
     * company overrides) with the provided defaults as fallback.
     */
    protected function computeContribution(
        float $grossSalary,
        string $code,
        float $defaultRate,
        ?float $defaultCap
    ): float {
        $rate = $this->resolveContributionRate($code, $defaultRate);
        $cap = $this->resolveContributionCap($code, $defaultCap);
        $base = $cap !== null ? min($grossSalary, $cap) : $grossSalary;

        return round($base * $rate / 100, 2);
    }

    /**
     * ZONE-INFRA (#1820): default = no professional-expenses deduction.
     * Countries with a legal abatement (CM 30 % capped 350 000 XAF,
     * CI, SN...) override this and apply it inside calculateIncomeTax().
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        return ['rate' => 0.0, 'cap' => null];
    }

    /**
     * ZONE-INFRA (#1820): default = no minimum bracket tax.
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        return 0.0;
    }

    /**
     * ZONE-INFRA (#1820): default = 13th month not legally mandatory
     * (contractual practice only, matching historic behaviour).
     */
    public function thirteenthMonthMandatory(): bool
    {
        return false;
    }

    /**
     * ZONE-INFRA (#1820): default = fully taxable like ordinary pay.
     */
    public function thirteenthMonthTaxTreatment(): string
    {
        return 'fully_taxable';
    }

    /**
     * ZONE-INFRA (#1820): default = no family allowance.
     */
    public function familyAllowancePerChild(): float
    {
        return 0.0;
    }
}
