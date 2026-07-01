<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Attendance\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\ApprovalWorkflow continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Attendance\Domain\Models\ApprovalWorkflow instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\ApprovalWorkflow::class, false)) {
    class_alias(
        App\Modules\Attendance\Domain\Models\ApprovalWorkflow::class,
        \App\Models\ApprovalWorkflow::class,
    );
}
