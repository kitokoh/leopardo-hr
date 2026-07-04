<?php
/**
 * Class alias — backward compat shim.
 *
 * The canonical model now lives in App\Modules\Attendance\Domain\Models.
 * This file is a thin redirect so that all existing code using
 * App\Models\KioskAnnouncement continues to work unchanged during migration.
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model in the module.
 * ✅  Once all usages are updated, delete this file.
 *
 * @deprecated Use App\Modules\Attendance\Domain\Models\KioskAnnouncement instead.
 */

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\KioskAnnouncement::class, false)) {
    class_alias(
        \App\Modules\Attendance\Domain\Models\KioskAnnouncement::class,
        \App\Models\KioskAnnouncement::class,
    );
}
