<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Core\Tenant\Infrastructure\Services\TenantCacheService
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Core\Tenant\Infrastructure\Services\TenantCacheService, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Cache;

class_alias(\App\Core\Tenant\Infrastructure\Services\TenantCacheService::class, __NAMESPACE__ . '\TenantCacheService');
