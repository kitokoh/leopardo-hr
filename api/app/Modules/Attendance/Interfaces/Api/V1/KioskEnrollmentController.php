<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use App\Modules\Attendance\Infrastructure\Services\KioskEnrollmentService;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskEnrollmentDecisionRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskEnrollmentStartRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskEnrollmentStatusRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ATT-004 (#6769) — enrôlement & pointage multi-méthodes versionné,
 * tenant-scoped (surface kiosque).
 *
 * Routes (auth appareil X-Kiosk-Token, middleware kiosk.device) :
 *   - POST /kiosks/{deviceCode}/enrollments          démarrer un enrôlement ;
 *   - POST /kiosks/{deviceCode}/enrollments/{id}/activate  valider (manager) ;
 *   - POST /kiosks/{deviceCode}/enrollments/{id}/revoke    révoquer (manager) ;
 *   - GET  /kiosks/{deviceCode}/enrollments/status   état neutre.
 *
 * Réponses NEUTRES : statuts/versions/ids uniquement — aucun gabarit, aucune
 * capture, aucun détail fournisseur (BIO-003 #6764, BIO-008 #6773).
 */
final class KioskEnrollmentController extends Controller
{
    public function __construct(
        private readonly KioskEnrollmentService $enrollmentService,
    ) {}

    public function start(KioskEnrollmentStartRequest $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->kioskFromRequest($request, $deviceCode);

        ['enrollment' => $enrollment] = $this->enrollmentService->start(
            kiosk: $kiosk,
            identifier: trim((string) $request->validated('identifier')),
            method: (string) $request->validated('method'),
            templatePayload: (string) $request->validated('template_payload'),
            provider: (string) $request->validated('provider'),
            correlationId: $request->validated('correlation_id') !== null
                ? (string) $request->validated('correlation_id')
                : null,
        );

        return new JsonResponse([
            'data' => $this->neutralEnrollment($enrollment),
        ], 201);
    }

    public function activate(KioskEnrollmentDecisionRequest $request, string $deviceCode, int $enrollment): JsonResponse
    {
        $kiosk = $this->kioskFromRequest($request, $deviceCode);
        $model = $this->resolveEnrollment($request, $enrollment);

        $activated = $this->enrollmentService->activate(
            kiosk: $kiosk,
            enrollment: $model,
            managerEmployeeId: (int) $request->validated('manager_employee_id'),
        );

        return new JsonResponse([
            'data' => $this->neutralEnrollment($activated),
        ]);
    }

    public function revoke(KioskEnrollmentDecisionRequest $request, string $deviceCode, int $enrollment): JsonResponse
    {
        $kiosk = $this->kioskFromRequest($request, $deviceCode);
        $model = $this->resolveEnrollment($request, $enrollment);

        $revoked = $this->enrollmentService->revoke(
            kiosk: $kiosk,
            enrollment: $model,
            managerEmployeeId: (int) $request->validated('manager_employee_id'),
        );

        return new JsonResponse([
            'data' => $this->neutralEnrollment($revoked),
        ]);
    }

    public function status(KioskEnrollmentStatusRequest $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->kioskFromRequest($request, $deviceCode);

        $status = $this->enrollmentService->status(
            kiosk: $kiosk,
            identifier: trim((string) $request->validated('identifier')),
        );

        return new JsonResponse([
            'data' => $status,
        ]);
    }

    /**
     * Résolution appareil : le middleware `kiosk.device` a déjà authentifié
     * le kiosque (X-Kiosk-Token) et posé le modèle en attribut.
     */
    private function kioskFromRequest(Request $request, string $deviceCode): AttendanceKiosk
    {
        $kiosk = $request->attributes->get('kiosk_device');

        if (! $kiosk instanceof AttendanceKiosk) {
            // Repli défensif (middleware non appliqué) — même sémantique 401.
            abort(401, 'INVALID_KIOSK_TOKEN');
        }

        $kiosk->setRelation('company', PlatformCompanyLookup::findOrFail((string) $kiosk->company_id));

        return $kiosk;
    }

    /**
     * Résolution d'un enrôlement dans le tenant du kiosque (fail-closed :
     * jamais de lecture cross-tenant — QLT-001 #6775).
     */
    private function resolveEnrollment(Request $request, int $enrollmentId): BiometricEnrollment
    {
        /** @var AttendanceKiosk $kiosk */
        $kiosk = $request->attributes->get('kiosk_device');

        $enrollment = BiometricEnrollment::query()
            ->where('company_id', $kiosk->company_id)
            ->whereKey($enrollmentId)
            ->first();

        if (! $enrollment) {
            throw (new ModelNotFoundException)->setModel(BiometricEnrollment::class);
        }

        return $enrollment;
    }

    /**
     * Sérialisation neutre d'un enrôlement (jamais le gabarit).
     *
     * @return array<string, mixed>
     */
    private function neutralEnrollment(BiometricEnrollment $enrollment): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'employee_id' => $enrollment->employee_id,
            'method' => $enrollment->method,
            'status' => $enrollment->status->value,
            'version' => $enrollment->version,
            'provider' => $enrollment->provider,
            'enrolled_via' => $enrollment->enrolled_via,
            'correlation_id' => $enrollment->correlation_id,
            'created_at' => $enrollment->created_at->toIso8601String(),
            'activated_at' => $enrollment->enrolled_at?->toIso8601String(),
            'revoked_at' => $enrollment->revoked_at?->toIso8601String(),
        ];
    }
}
