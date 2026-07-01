<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Tenant\Domain\Models\Company
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Core\Tenant\Domain\Models\Company, delete this file.
 *
 * @see \App\Core\Tenant\Domain\Models\Company
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Core\Tenant\Domain\Models\Company::class, __NAMESPACE__ . '\\Company');
