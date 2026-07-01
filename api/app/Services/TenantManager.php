<?php
/**
 * Backward-compat alias.
 *
 * Canonical: App\Core\Tenant\TenantManager
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Core\Tenant\TenantManager, delete this file.
 *
 * @deprecated Use App\Core\Tenant\TenantManager
 */

declare(strict_types=1);

namespace App\Services;

if (! class_exists(\App\Services\TenantManager::class, false)) {
    class_alias(
        \App\Core\Tenant\TenantManager::class,
        \App\Services\TenantManager::class,
    );
}
