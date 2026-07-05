<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Payroll\CountryRules;

class_alias(\App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules::class, __NAMESPACE__ . '\FrancePayrollRules');
