<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner::class, __NAMESPACE__ . '\NotificationPreferenceProvisioner');
