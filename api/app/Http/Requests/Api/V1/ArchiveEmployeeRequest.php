<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\HR\Interfaces\Api\V1\Requests\ArchiveEmployeeRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\HR\Interfaces\Api\V1\Requests\ArchiveEmployeeRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class_alias(\App\Modules\HR\Interfaces\Api\V1\Requests\ArchiveEmployeeRequest::class, __NAMESPACE__ . '\\ArchiveEmployeeRequest');
