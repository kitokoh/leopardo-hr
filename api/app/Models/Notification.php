<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Notification\Domain\Models\Notification
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\Notification\Domain\Models\Notification, delete this file.
 *
 * @see \App\Modules\Notification\Domain\Models\Notification
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\Notification\Domain\Models\Notification::class, __NAMESPACE__ . '\\Notification');
