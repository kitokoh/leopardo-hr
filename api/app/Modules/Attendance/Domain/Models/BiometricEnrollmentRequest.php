<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class BiometricEnrollmentRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'approver_employee_id',
        'status',
        'requested_face_enabled',
        'requested_fingerprint_enabled',
        'requested_face_reference_path',
        'requested_fingerprint_reference_path',
        'requested_fingerprint_device_id',
        'request_source',
        'employee_note',
        'manager_note',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];
}

