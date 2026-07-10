<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules::class, __NAMESPACE__ . '\AbstractCountryRules');
