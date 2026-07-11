<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Payroll\Infrastructure\Services\PayrollCalculator
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Payroll\Infrastructure\Services\PayrollCalculator, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Payroll;

class_alias(\App\Modules\Payroll\Infrastructure\Services\PayrollCalculator::class, __NAMESPACE__ . '\PayrollCalculator');
