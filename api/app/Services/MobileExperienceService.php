<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\HR\Infrastructure\Services\MobileExperienceService
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Modules\HR\Infrastructure\Services\MobileExperienceService, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Modules\HR\Infrastructure\Services\MobileExperienceService::class, __NAMESPACE__ . '\\MobileExperienceService');
