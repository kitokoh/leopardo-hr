<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Notification\Domain\Models\DeviceToken
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\Notification\Domain\Models\DeviceToken, delete this file.
 *
 * @see \App\Modules\Notification\Domain\Models\DeviceToken
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\Notification\Domain\Models\DeviceToken::class, __NAMESPACE__ . '\\DeviceToken');
