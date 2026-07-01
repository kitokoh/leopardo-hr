<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Absence\Interfaces\Api\V1\Requests\AbsenceIndexRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\Absence\Interfaces\Api\V1\Requests\AbsenceIndexRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Absence;

class_alias(\App\Modules\Absence\Interfaces\Api\V1\Requests\AbsenceIndexRequest::class, __NAMESPACE__ . '\\AbsenceIndexRequest');
