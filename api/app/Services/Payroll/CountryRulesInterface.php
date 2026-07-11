<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Payroll\Domain\Contracts\CountryRulesInterface
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Payroll\Domain\Contracts\CountryRulesInterface, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Payroll;

class_alias(\App\Modules\Payroll\Domain\Contracts\CountryRulesInterface::class, __NAMESPACE__ . '\CountryRulesInterface');
