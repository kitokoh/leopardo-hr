<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Tenant\Domain\Models\CompanySetting
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Core\Tenant\Domain\Models\CompanySetting, delete this file.
 *
 * @see \App\Core\Tenant\Domain\Models\CompanySetting
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Core\Tenant\Domain\Models\CompanySetting::class, __NAMESPACE__ . '\\CompanySetting');
