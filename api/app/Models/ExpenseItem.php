<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Expense\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\ExpenseItem continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Expense\Domain\Models\ExpenseItem instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\ExpenseItem::class, false)) {
    class_alias(
        App\Modules\Expense\Domain\Models\ExpenseItem::class,
        \App\Models\ExpenseItem::class,
    );
}
