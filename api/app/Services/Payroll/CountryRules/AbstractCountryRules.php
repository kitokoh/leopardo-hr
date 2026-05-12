<?php

namespace App\Services\Payroll\CountryRules;

use App\Services\Payroll\CountryRulesInterface;

abstract class AbstractCountryRules implements CountryRulesInterface
{
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
