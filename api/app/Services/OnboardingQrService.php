<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService::class, __NAMESPACE__ . '\\OnboardingQrService');
