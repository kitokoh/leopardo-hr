<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

class_alias(\App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeRequest::class, __NAMESPACE__ . '\\UpdateEmployeeRequest');
