<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Billing\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\FeaturePlanMatrix continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Billing\Domain\Models\FeaturePlanMatrix instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\FeaturePlanMatrix::class, false)) {
    class_alias(
        App\Modules\Billing\Domain\Models\FeaturePlanMatrix::class,
        \App\Models\FeaturePlanMatrix::class,
    );
}
