<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Platform\Infrastructure\Services\PlatformCompanyHealthService
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Modules\Platform\Infrastructure\Services\PlatformCompanyHealthService, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Modules\Platform\Infrastructure\Services\PlatformCompanyHealthService::class, __NAMESPACE__ . '\\PlatformCompanyHealthService');
