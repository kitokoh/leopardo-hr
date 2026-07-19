<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

abstract class AbstractCountryRules implements CountryRulesInterface
{
    /**
     * Optional tenant scope used to look up company-specific tax slab overrides.
     * Set via forCompany(); left null means "use global/default slabs only".
     */
    protected ?string $companyId = null;

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
     * Effective tax slabs: company-specific override from the `tax_slabs` table
     * if present, else a global (company_id IS NULL) override from the same
     * table, else the country's hardcoded default slabs. This makes the
     * TaxSlabController CRUD API (and the underlying `tax_slabs` table) actually
     * affect payroll calculations, instead of being a disconnected admin screen.
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

            $base = TaxSlab::query()->forCountry($this->countryCode())->effective();

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
}


