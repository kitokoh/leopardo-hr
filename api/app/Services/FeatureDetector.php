<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Feature\Infrastructure\Services\FeatureDetector
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Core\Feature\Infrastructure\Services\FeatureDetector, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Core\Feature\Infrastructure\Services\FeatureDetector::class, __NAMESPACE__ . '\\FeatureDetector');
