<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Auth\Infrastructure\Services\SuperAdminService
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Core\Auth\Infrastructure\Services\SuperAdminService, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Core\Auth\Infrastructure\Services\SuperAdminService::class, __NAMESPACE__ . '\\SuperAdminService');
