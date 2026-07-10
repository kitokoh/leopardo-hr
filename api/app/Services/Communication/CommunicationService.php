<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Notification\Infrastructure\Services\CommunicationService
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Notification\Infrastructure\Services\CommunicationService, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Modules\Notification\Infrastructure\Services\CommunicationService::class, __NAMESPACE__ . '\CommunicationService');
