<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Modules\Attendance\Infrastructure\Services\TraccarService
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Modules\Attendance\Infrastructure\Services\TraccarService, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Modules\Attendance\Infrastructure\Services\TraccarService::class, __NAMESPACE__ . '\TraccarService');
