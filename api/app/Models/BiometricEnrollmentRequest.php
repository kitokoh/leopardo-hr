<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $approver_employee_id
 * @property string $status
 * @property bool $requested_face_enabled
 * @property bool $requested_fingerprint_enabled
 * @property string|null $requested_face_reference_path
 * @property string|null $requested_fingerprint_reference_path
 * @property int|null $requested_fingerprint_device_id
 * @property string $request_source
 * @property string|null $employee_note
 * @property string|null $manager_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BiometricEnrollmentRequest extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'biometric_enrollment_requests';

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

    protected $casts = [
        'requested_face_enabled' => 'boolean',
        'requested_fingerprint_enabled' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }
}
