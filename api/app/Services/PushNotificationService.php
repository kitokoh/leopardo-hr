<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Notification\Infrastructure\Services\PushNotificationService
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Modules\Notification\Infrastructure\Services\PushNotificationService, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Modules\Notification\Infrastructure\Services\PushNotificationService::class, __NAMESPACE__ . '\\PushNotificationService');
