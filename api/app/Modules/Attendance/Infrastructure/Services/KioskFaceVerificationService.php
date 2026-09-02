<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Domain\ValueObjects\FaceVerificationRequest;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vérification faciale au pointage kiosque (BIO-004, #6765).
 *
 * Flux 1:1 : l'employé est identifié AVANT la comparaison (matricule, badge,
 * email, zkteco_id) → capture → contrôle qualité/liveness → comparaison au
 * gabarit ACTIF (BIO-003) → événement de pointage → confirmation.
 *
 * Garanties :
 *   - aucun échec facial ne crée de présence ni d'absence automatique ;
 *   - le moteur est remplaçable par configuration (défaut fail-closed :
 *     provider_unavailable) ;
 *   - chaque tentative est auditée SANS capture ni gabarit (BIO-008) ;
 *   - la capture temporaire est supprimée après traitement (try/finally) ;
 *   - les événements sont idempotents et corrélés (`correlation_id`).
 */
final class KioskFaceVerificationService
{
    public function __construct(
        private readonly FaceVerificationPort $faceVerification,
        private readonly KioskAttendanceService $kioskAttendance,
        private readonly BiometricAuditLogger $audit,
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * Identifie, vérifie le visage puis enregistre le pointage.
     *
     * @return array{status: FaceVerificationStatus, log: AttendanceLog|null, correlation_id: string, reason_code: string|null, fallback_methods: list<string>}
     */
    public function verifyAndPunch(
        AttendanceKiosk $kiosk,
        string $identifier,
        UploadedFile $capture,
        string $action = 'check_in',
        string $workType = 'normal',
        ?string $deviceEventId = null,
    ): array {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $capture, $action, $workType, $deviceEventId): array {
            $correlationId = $deviceEventId !== null && $deviceEventId !== ''
                ? $deviceEventId
                : (string) Str::uuid();

            $employee = $this->resolveEmployee($kiosk, $identifier);
            if ($employee === null) {
                $this->audit->log(
                    companyId: (string) $kiosk->company_id,
                    event: 'verification.rejected',
                    kioskId: (int) $kiosk->id,
                    siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
                    resultCode: 'EMPLOYEE_NOT_FOUND',
                    correlationId: $correlationId,
                );

                return $this->outcome(FaceVerificationStatus::Rejected, null, $correlationId, 'EMPLOYEE_NOT_FOUND', $this->kioskFallbacks($kiosk));
            }

            if (! (bool) $employee->biometric_face_enabled) {
                return $this->outcome(FaceVerificationStatus::Rejected, null, $correlationId, 'FACE_NOT_ENABLED', $this->kioskFallbacks($kiosk));
            }

            $enrollment = BiometricEnrollment::query()
                ->usableFor((int) $employee->id, 'face')
                ->latest('version')
                ->first();

            if ($enrollment === null) {
                $this->audit->log(
                    companyId: (string) $kiosk->company_id,
                    event: 'verification.rejected',
                    employeeId: (int) $employee->id,
                    kioskId: (int) $kiosk->id,
                    siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
                    method: 'face',
                    resultCode: 'FACE_NOT_ENROLLED',
                    correlationId: $correlationId,
                );

                return $this->outcome(FaceVerificationStatus::Rejected, null, $correlationId, 'FACE_NOT_ENROLLED', $this->kioskFallbacks($kiosk));
            }

            $capturePath = $this->storeCapture($kiosk, $capture);
            $resultCode = null;

            try {
                $result = $this->faceVerification->verify(new FaceVerificationRequest(
                    correlationId: $correlationId,
                    templateReference: 'biometric_enrollment:'.(int) $enrollment->id,
                    captureReference: (string) $capturePath,
                ));

                $this->audit->log(
                    companyId: (string) $kiosk->company_id,
                    event: 'verification.'.$result->status->value,
                    employeeId: (int) $employee->id,
                    kioskId: (int) $kiosk->id,
                    siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
                    method: 'face',
                    resultCode: $this->stableReasonCode($result->status),
                    correlationId: $correlationId,
                );

                if (! $result->isVerified()) {
                    $resultCode = $this->stableReasonCode($result->status);

                    return $this->outcome($result->status, null, $correlationId, $resultCode, $this->kioskFallbacks($kiosk));
                }

                // Vérifié → événement de pointage (méthode réellement utilisée :
                // `face` — matrice kiosque + flags employé re-vérifiés côté
                // serveur par KioskAttendanceService).
                $log = $this->kioskAttendance->punch(
                    kiosk: $kiosk,
                    identifier: $identifier,
                    action: $action,
                    workType: $workType,
                    method: 'face',
                );

                return $this->outcome(FaceVerificationStatus::Verified, $log, $correlationId);
            } finally {
                // Nettoyage systématique de la capture temporaire (RGPD).
                Storage::disk('local')->delete($capturePath);
            }
        });
    }

    private function resolveEmployee(AttendanceKiosk $kiosk, string $identifier): ?Employee
    {
        try {
            return Employee::query()
                ->where('company_id', $kiosk->company_id)
                ->where(function ($query) use ($identifier): void {
                    $query
                        ->where('email', $identifier)
                        ->orWhere('matricule', $identifier)
                        ->orWhere('zkteco_id', $identifier)
                        ->orWhere('badge_number', $identifier);
                })
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Stocke la capture dans un dossier temporaire tenant-scoped.
     */
    private function storeCapture(AttendanceKiosk $kiosk, UploadedFile $capture): string
    {
        $extension = (string) ($capture->getClientOriginalExtension() !== ''
            ? $capture->getClientOriginalExtension()
            : 'jpg');

        $path = 'biometric-captures/'.$kiosk->company_id.'/'.Str::uuid().'.'.$extension;

        $stored = $capture->storeAs(dirname($path), basename($path), 'local');
        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('Face capture could not be stored.');
        }

        return $stored;
    }

    /**
     * Code machine stable exposé à l'interface (jamais le code du fournisseur).
     */
    private function stableReasonCode(FaceVerificationStatus $status): ?string
    {
        return match ($status) {
            FaceVerificationStatus::Verified => null,
            FaceVerificationStatus::Rejected => 'VERIFICATION_REJECTED',
            FaceVerificationStatus::QualityFailed => 'VERIFICATION_QUALITY_FAILED',
            FaceVerificationStatus::LivenessFailed => 'VERIFICATION_LIVENESS_FAILED',
            FaceVerificationStatus::ProviderUnavailable => 'FACE_PROVIDER_NOT_CONFIGURED',
            FaceVerificationStatus::Timeout => 'FACE_VERIFICATION_TIMEOUT',
        };
    }

    /**
     * Méthodes de repli proposées à l'interface (BIO-006) : la matrice
     * activée du kiosque, sans `face`.
     *
     * @return list<string>
     */
    private function kioskFallbacks(AttendanceKiosk $kiosk): array
    {
        return array_values(array_filter(
            $kiosk->resolvedPunchMethods(),
            static fn (string $method): bool => $method !== 'face',
        ));
    }

    /**
     * @param  AttendanceLog|null  $log
     * @param  list<string>  $fallbackMethods
     * @return array{status: FaceVerificationStatus, log: AttendanceLog|null, correlation_id: string, reason_code: string|null, fallback_methods: list<string>}
     */
    private function outcome(
        FaceVerificationStatus $status,
        ?AttendanceLog $log,
        string $correlationId,
        ?string $reasonCode = null,
        array $fallbackMethods = [],
    ): array {
        return [
            'status' => $status,
            'log' => $log,
            'correlation_id' => $correlationId,
            'reason_code' => $reasonCode,
            'fallback_methods' => $fallbackMethods,
        ];
    }
}
