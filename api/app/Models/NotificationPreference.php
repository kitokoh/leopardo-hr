<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Notification\Domain\Models\NotificationPreference
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\Notification\Domain\Models\NotificationPreference, delete this file.
 *
 * @see \App\Modules\Notification\Domain\Models\NotificationPreference
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\Notification\Domain\Models\NotificationPreference::class, __NAMESPACE__ . '\\NotificationPreference');
