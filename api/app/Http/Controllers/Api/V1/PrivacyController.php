<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\PaySlip;
use App\Models\PrivacyRequest;
use App\Services\DataAccessAuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function __construct(
        private readonly DataAccessAuditLogger $dataAccessAuditLogger,
    ) {}

    public function export(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $this->dataAccessAuditLogger->record($request, $employee, 'hr_data.privacy_exported', $employee, [
            'resource' => 'privacy_export',
            'format_version' => '2026-05-14',
        ]);

        return new JsonResponse([
            'data' => [
                'employee' => $this->employeePayload($employee),
                'activity_summary' => [
                    'attendance_logs_count' => $this->countEmployeeRows(AttendanceLog::class, $employee),
                    'absence_requests_count' => $this->countEmployeeRows(Absence::class, $employee),
                    'pay_slips_count' => $this->countEmployeeRows(PaySlip::class, $employee),
                    'expense_claims_count' => $this->countEmployeeRows(ExpenseClaim::class, $employee),
                ],
                'privacy' => [
                    'exported_at' => now()->toIso8601String(),
                    'scope' => 'authenticated_employee_self_service',
                    'format_version' => '2026-05-14',
                ],
            ],
        ]);
    }

    public function storeDeletionRequest(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $privacyRequest = PrivacyRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'type' => 'deletion',
            'status' => 'received',
            'requested_payload' => [
                'reason' => $validated['reason'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_at' => now()->toIso8601String(),
                'destructive_action' => false,
            ],
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $privacyRequest->id,
                'type' => $privacyRequest->type,
                'status' => $privacyRequest->status,
                'message' => 'Deletion request received for HR/legal review.',
            ],
        ], 202);
    }

    public function updateBiometricConsent(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'consented' => ['required', 'boolean'],
        ]);

        $consented = (bool) $validated['consented'];
        $employee->forceFill([
            'biometric_face_enabled' => $consented ? $employee->biometric_face_enabled : false,
            'biometric_fingerprint_enabled' => $consented ? $employee->biometric_fingerprint_enabled : false,
            'biometric_face_reference_path' => $consented ? $employee->biometric_face_reference_path : null,
            'biometric_fingerprint_reference_path' => $consented ? $employee->biometric_fingerprint_reference_path : null,
            'biometric_consent_at' => $consented ? ($employee->biometric_consent_at ?? now()) : null,
        ])->save();

        PrivacyRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'type' => 'biometric_consent',
            'status' => 'completed',
            'processed_at' => now(),
            'requested_payload' => [
                'consented' => $consented,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'processed_at' => now()->toIso8601String(),
            ],
        ]);

        return new JsonResponse([
            'data' => [
                'biometric_face_enabled' => $employee->biometric_face_enabled,
                'biometric_fingerprint_enabled' => $employee->biometric_fingerprint_enabled,
                'biometric_consent_at' => optional($employee->biometric_consent_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'company_id' => $employee->company_id,
            'matricule' => $employee->matricule,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'preferred_name' => $employee->preferred_name,
            'email' => $employee->email,
            'personal_email' => $employee->personal_email,
            'phone' => $employee->phone,
            'role' => $employee->role,
            'manager_role' => $employee->manager_role,
            'status' => $employee->status,
            'preferred_language' => $employee->preferred_language,
            'biometric_face_enabled' => $employee->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $employee->biometric_fingerprint_enabled,
            'biometric_consent_at' => optional($employee->biometric_consent_at)->toIso8601String(),
            'created_at' => optional($employee->created_at)->toIso8601String(),
            'updated_at' => optional($employee->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function countEmployeeRows(string $modelClass, Employee $employee): int
    {
        return $modelClass::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->count();
    }
}
