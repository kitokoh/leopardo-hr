<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Tenant\Domain\Models\SuperAdmin
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Core\Tenant\Domain\Models\SuperAdmin, delete this file.
 *
 * @see \App\Core\Tenant\Domain\Models\SuperAdmin
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Core\Tenant\Domain\Models\SuperAdmin::class, __NAMESPACE__ . '\\SuperAdmin');
