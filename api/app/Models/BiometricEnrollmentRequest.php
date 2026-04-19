<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricEnrollmentRequest extends Model
{
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }
}
