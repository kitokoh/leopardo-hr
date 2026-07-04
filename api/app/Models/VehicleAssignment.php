<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Fleet\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\VehicleAssignment continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Fleet\Domain\Models\VehicleAssignment instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\VehicleAssignment::class, false)) {
    class_alias(
        \App\Modules\Fleet\Domain\Models\VehicleAssignment::class,
        \App\Models\VehicleAssignment::class,
    );
}
