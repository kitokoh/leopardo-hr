<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Payroll\Domain\Models\Commission
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\Payroll\Domain\Models\Commission, delete this file.
 *
 * @see \App\Modules\Payroll\Domain\Models\Commission
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\Payroll\Domain\Models\Commission::class, __NAMESPACE__ . '\\Commission');
