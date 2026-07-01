<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\HR\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\Evaluation continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\HR\Domain\Models\Evaluation instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\Evaluation::class, false)) {
    class_alias(
        App\Modules\HR\Domain\Models\Evaluation::class,
        \App\Models\Evaluation::class,
    );
}
