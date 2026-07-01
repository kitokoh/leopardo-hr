<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\HR\Domain\Models\PrivacyRequest
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Modules\HR\Domain\Models\PrivacyRequest, delete this file.
 *
 * @see \App\Modules\HR\Domain\Models\PrivacyRequest
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\HR\Domain\Models\PrivacyRequest::class, __NAMESPACE__ . '\\PrivacyRequest');
