<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules::class, __NAMESPACE__ . '\TunisiaPayrollRules');
