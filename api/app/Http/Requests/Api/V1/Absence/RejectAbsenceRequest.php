<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Absence\Interfaces\Api\V1\Requests\RejectAbsenceRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\Absence\Interfaces\Api\V1\Requests\RejectAbsenceRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Absence;

class_alias(\App\Modules\Absence\Interfaces\Api\V1\Requests\RejectAbsenceRequest::class, __NAMESPACE__ . '\\RejectAbsenceRequest');
