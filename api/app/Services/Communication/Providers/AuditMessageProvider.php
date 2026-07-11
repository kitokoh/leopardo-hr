<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Notification\Infrastructure\Services\Providers\AuditMessageProvider
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Notification\Infrastructure\Services\Providers\AuditMessageProvider, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Communication\Providers;

class_alias(\App\Modules\Notification\Infrastructure\Services\Providers\AuditMessageProvider::class, __NAMESPACE__ . '\AuditMessageProvider');
