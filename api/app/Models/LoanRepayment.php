<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Payroll\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\LoanRepayment continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Payroll\Domain\Models\LoanRepayment instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\LoanRepayment::class, false)) {
    class_alias(
        App\Modules\Payroll\Domain\Models\LoanRepayment::class,
        \App\Models\LoanRepayment::class,
    );
}
