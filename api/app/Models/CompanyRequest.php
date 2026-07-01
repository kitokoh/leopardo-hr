<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Tenant\Domain\Models\CompanyRequest
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Core\Tenant\Domain\Models\CompanyRequest, delete this file.
 *
 * @see \App\Core\Tenant\Domain\Models\CompanyRequest
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Core\Tenant\Domain\Models\CompanyRequest::class, __NAMESPACE__ . '\\CompanyRequest');
