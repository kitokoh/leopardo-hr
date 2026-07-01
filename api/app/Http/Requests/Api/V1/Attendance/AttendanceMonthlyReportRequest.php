<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceMonthlyReportRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceMonthlyReportRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

class_alias(\App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceMonthlyReportRequest::class, __NAMESPACE__ . '\\AttendanceMonthlyReportRequest');
