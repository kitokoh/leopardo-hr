<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Modules\HR\Infrastructure\Services\PiiLifecycleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function __construct(
        private readonly DataAccessAuditLogger $dataAccessAuditLogger,
        private readonly PiiLifecycleService $piiLifecycle,
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
            'data' => $this->piiLifecycle->exportBundle($employee),
        ]);
    }

    public function storeDeletionRequest(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $privacyRequest = $this->piiLifecycle->requestDeletion(
            $employee,
            $validated['reason'] ?? null,
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        return new JsonResponse([
            'data' => [
                'id' => $privacyRequest->id,
                'type' => $privacyRequest->type,
                'status' => $privacyRequest->status,
                'message' => __('errors.DELETION_REQUEST_RECEIVED'),
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

        \App\Modules\HR\Domain\Models\PrivacyRequest::query()->create([
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
}
