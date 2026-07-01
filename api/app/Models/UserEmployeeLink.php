<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\HR\Domain\Models\UserEmployeeLink
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\HR\Domain\Models\UserEmployeeLink, delete this file.
 *
 * @see \App\Modules\HR\Domain\Models\UserEmployeeLink
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\HR\Domain\Models\UserEmployeeLink::class, __NAMESPACE__ . '\\UserEmployeeLink');
