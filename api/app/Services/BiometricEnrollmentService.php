<?php

namespace App\Services;

use App\Models\BiometricEnrollmentRequest;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BiometricEnrollmentService
{
    public function submit(Employee $employee, array $payload, ?UploadedFile $faceImage = null): BiometricEnrollmentRequest
    {
        $facePath = $payload['requested_face_reference_path'] ?? null;

        if ($faceImage) {
            $facePath = $faceImage->store('biometrics/faces/'.$employee->company_id, 'local');
        }

        /** @var BiometricEnrollmentRequest $request */
        $request = BiometricEnrollmentRequest::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'status' => 'pending',
            ],
            [
                'company_id' => $employee->company_id,
                'requested_face_enabled' => (bool) ($payload['requested_face_enabled'] ?? false),
                'requested_fingerprint_enabled' => (bool) ($payload['requested_fingerprint_enabled'] ?? false),
                'requested_face_reference_path' => $facePath,
                'requested_fingerprint_reference_path' => $payload['requested_fingerprint_reference_path'] ?? null,
                'requested_fingerprint_device_id' => $payload['requested_fingerprint_device_id'] ?? null,
                'request_source' => $payload['request_source'] ?? 'mobile',
                'employee_note' => $payload['employee_note'] ?? null,
                'submitted_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
                'approver_employee_id' => null,
                'manager_note' => null,
            ],
        );

        return $request->fresh();
    }

    public function approve(Employee $approver, BiometricEnrollmentRequest $request, ?string $managerNote = null): BiometricEnrollmentRequest
    {
        return DB::transaction(function () use ($approver, $request, $managerNote): BiometricEnrollmentRequest {
            /** @var Employee $employee */
            $employee = Employee::query()->findOrFail($request->employee_id);

            $employee->forceFill([
                'biometric_face_enabled' => $request->requested_face_enabled,
                'biometric_fingerprint_enabled' => $request->requested_fingerprint_enabled,
                'biometric_face_reference_path' => $request->requested_face_reference_path,
                'biometric_fingerprint_reference_path' => $request->requested_fingerprint_reference_path ?? $request->requested_fingerprint_device_id,
                'biometric_consent_at' => now(),
            ])->save();

            $request->forceFill([
                'status' => 'approved',
                'approver_employee_id' => $approver->id,
                'manager_note' => $managerNote,
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();

            return $request->fresh();
        });
    }

    public function reject(Employee $approver, BiometricEnrollmentRequest $request, ?string $managerNote = null): BiometricEnrollmentRequest
    {
        $request->forceFill([
            'status' => 'rejected',
            'approver_employee_id' => $approver->id,
            'manager_note' => $managerNote,
            'approved_at' => null,
            'rejected_at' => now(),
        ])->save();

        return $request->fresh();
    }
}
