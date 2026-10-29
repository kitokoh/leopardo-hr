<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Controllers;

use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Application\Actions\BuildKioskDeviceConfig;
use App\Modules\Attendance\Application\Actions\BuildKioskEmployeeInfo;
use App\Modules\Attendance\Application\Actions\BuildKioskRoster;
use App\Modules\Attendance\Application\Actions\ProvisionKiosk;
use App\Modules\Attendance\Application\Actions\RevokeKiosk;
use App\Modules\Attendance\Application\Actions\RotateKioskToken;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskAttendanceService;
use App\Modules\Attendance\Infrastructure\Services\KioskFaceVerificationService;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskPunchRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskSyncRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\KioskVerifyFaceRequest;
use App\Shared\Contracts\HR\OnboardingQrInterface;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
        private readonly OnboardingQrInterface $onboardingQr,
        private readonly KioskFaceVerificationService $faceVerification,
        private readonly TenantManager $tenantManager,
        private readonly BuildKioskRoster $buildKioskRoster,
        private readonly BuildKioskEmployeeInfo $buildKioskEmployeeInfo,
        private readonly ProvisionKiosk $provisionKiosk,
        private readonly RevokeKiosk $revokeKiosk,
        private readonly RotateKioskToken $rotateKioskToken,
        private readonly BuildKioskDeviceConfig $buildKioskDeviceConfig,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $company = currentCompany();
        /** @var Employee $actor */
        $actor = $request->user();

        abort_unless($actor?->isManager(), 403, 'FORBIDDEN');

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doRegister($request, $company, $actor),
        );
    }

    private function doRegister(Request $request, Company $company, Employee $actor): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
            'trusted_device_label' => ['nullable', 'string', 'max:120'],
            // BIO-006 (#6767) : matrice de méthodes du kiosque (optionnelle).
            'punch_methods' => ['nullable', 'array', 'min:1'],
            'punch_methods.*' => ['required', 'in:fingerprint,face,badge,pin,manager,card'],
            // BIO-005 (#6766) : site d'affectation (fixé au provisioning).
            'site_id' => ['nullable', 'integer'],
        ]);

        // #7962 — la création, la normalisation de la matrice de méthodes et la
        // garde fail-closed de provisioning vivent dans l'Action ; le
        // contrôleur ne fait plus que l'orchestration HTTP.
        $provisioned = $this->provisionKiosk->execute($company, $validated);

        return new JsonResponse([
            'data' => array_replace($this->serializeKiosk($provisioned['kiosk']), [
                'device_code' => $provisioned['device_code'],
                'sync_token' => $provisioned['sync_token'],
            ]),
        ], 201);
    }

    public function punch(KioskPunchRequest $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim((string) $request->validated('identifier')),
            action: $request->validated('action') ?? 'check_in',
            workType: $request->validated('work_type') ?? 'normal',
            method: $request->validated('method') !== null ? (string) $request->validated('method') : null,
            managerEmployeeId: $request->validated('manager_employee_id') !== null ? (int) $request->validated('manager_employee_id') : null,
            deviceEventId: $request->validated('device_event_id') !== null ? (string) $request->validated('device_event_id') : null,
            biometricProof: $request->validated('biometric_proof') !== null ? (string) $request->validated('biometric_proof') : null,
        );

        // #7958 : une biométrie réclamée persistée `manual` = identité non
        // vérifiée (pas de preuve device) — signalé explicitement au client.
        $requestedMethod = $request->validated('method');
        $unverified = is_string($requestedMethod)
            && in_array($requestedMethod, ['fingerprint', 'face'], true)
            && $log->method === 'manual';

        // REST convention: 201 Created for check_in, 200 OK for check_out
        $action = $request->validated('action') ?? 'check_in';
        $statusCode = $action === 'check_in' ? 201 : 200;

        return new JsonResponse([
            'data' => [
                'employee_id' => $log->employee_id,
                'date' => $log->date?->format('Y-m-d'),
                'check_in' => optional($log->check_in)->toIso8601String(),
                'check_out' => optional($log->check_out)->toIso8601String(),
                'method' => $log->method,
                'work_type' => $log->work_type,
                'session_number' => $log->session_number,
                'status' => $log->status,
                // #7958 : true quand la méthode réclamée (fingerprint/face)
                // a été dégradée en `manual` faute de preuve device.
                'unverified' => $unverified,
                // BIO-007 (#6772) : rejeu idempotent signalé au client.
                'replayed' => $log->external_event_id !== null
                    && $request->validated('device_event_id') !== null
                    && $log->external_event_id === $request->validated('device_event_id'),
            ],
        ], $statusCode);
    }

    public function roster(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doRoster($company, $deviceCode),
        );
    }

    private function doRoster(Company $company, string $deviceCode): JsonResponse
    {
        // #7962 — la requête et la sérialisation vivent dans l'Action ; le
        // contrôleur ne garde que l'orchestration HTTP.
        $items = $this->buildKioskRoster->execute($company);

        return new JsonResponse([
            'data' => [
                'device_code' => $deviceCode,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'employees' => $items,
            ],
        ]);
    }

    public function sync(KioskSyncRequest $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        app()->instance('current_company', $kiosk->company);

        return $this->withTenantSearchPath(
            $kiosk->company,
            // #5588 (follow-up) : transmettre le device_code EN CLAIR (celui de
            // l'URL) — le modèle ne porte plus que la dérivation (64 hex).
            fn (): JsonResponse => $this->doSync(
                $kiosk,
                $request->validated(),
                $deviceCode,
                // BIO-007 (#6772) : le token en clair (header) sert de clé de
                // vérification HMAC du batch offline signé.
                (string) $request->header('X-Kiosk-Token', ''),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function doSync(AttendanceKiosk $kiosk, array $validated, string $deviceCode, string $plainSyncToken): JsonResponse
    {
        $result = $this->kioskAttendanceService->syncPunches(
            $kiosk,
            $validated['events'],
            $deviceCode,
            deviceState: isset($validated['device_state']) && is_array($validated['device_state']) ? $validated['device_state'] : null,
            plainSyncToken: $plainSyncToken,
        );

        // #3587 — le bridge isole les événements refusés en dead-letter au
        // lieu de les marquer synced : la réponse détaille désormais chaque
        // skip (external_event_id + raison). Contrat additif :
        // processed_count/processed_log_ids inchangés.
        return new JsonResponse([
            'data' => [
                'processed_count' => count($result['processed']),
                'processed_log_ids' => $result['processed'],
                'skipped_count' => count($result['skipped']),
                'skipped' => $result['skipped'],
                'last_sync_at' => $kiosk->fresh()?->last_sync_at?->toIso8601String(),
                // BIO-007 (#6772) : état de synchronisation visible (compteur
                // acquitté, heure serveur, politique offline appliquée).
                'server_time' => now()->toIso8601String(),
                'acked_event_counter' => (int) $kiosk->fresh()?->acked_event_counter,
                'offline_policy' => $this->buildKioskDeviceConfig->execute(BuildKioskDeviceConfig::VIEW_OFFLINE_POLICY)['offline_policy'],
            ],
        ]);
    }

    public function employeeInfo(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doEmployeeInfo($company, $validated),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function doEmployeeInfo(Company $company, array $validated): JsonResponse
    {
        // #7962 — résolution employé + présence du jour + soldes de congés
        // déplacés dans l'Action ; le contrôleur ne garde que l'enveloppe HTTP.
        return new JsonResponse([
            'data' => $this->buildKioskEmployeeInfo->execute($company, $validated),
        ]);
    }

    public function announcements(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doAnnouncements($kiosk, $company, $deviceCode),
        );
    }

    private function doAnnouncements(AttendanceKiosk $kiosk, Company $company, string $deviceCode): JsonResponse
    {
        if (! Schema::hasTable('kiosk_announcements')) {
            return new JsonResponse(['data' => []]);
        }

        try {
            $columns = Schema::getColumnListing('kiosk_announcements');
            $hasColumn = fn (string $column): bool => in_array($column, $columns, true);

            if (! $hasColumn('company_id')) {
                return new JsonResponse(['data' => []]);
            }

            $announcements = DB::table('kiosk_announcements')
                ->where('company_id', $company->id)
                ->when($hasColumn('is_active'), fn ($query) => $query->where('is_active', true))
                ->when($hasColumn('starts_at'), function ($query): void {
                    $query->where(function ($windowQuery): void {
                        $windowQuery->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                    });
                })
                ->when($hasColumn('expires_at'), function ($query): void {
                    $query->where(function ($windowQuery): void {
                        $windowQuery->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    });
                })
                ->when($hasColumn('priority'), fn ($query) => $query->orderByDesc('priority'))
                ->when(
                    $hasColumn('created_at'),
                    fn ($query) => $query->orderByDesc('created_at'),
                    fn ($query) => $hasColumn('id') ? $query->orderByDesc('id') : $query
                )
                ->limit(10)
                ->get()
                ->map(fn ($announcement) => [
                    'id' => $announcement->id ?? null,
                    'title' => $announcement->title ?? '',
                    'body' => $announcement->body ?? '',
                    'priority' => $announcement->priority ?? 'normal',
                    'starts_at' => $this->nullableIsoString($announcement->starts_at ?? null),
                    'expires_at' => $this->nullableIsoString($announcement->expires_at ?? null),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Kiosk announcements skipped because the tenant table is not queryable.', [
                'company_id' => $company->id,
                'device_code' => $deviceCode,
                'error' => $exception->getMessage(),
            ]);

            return new JsonResponse(['data' => []]);
        }

        return new JsonResponse(['data' => $announcements]);
    }

    public function leaveBalance(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doLeaveBalance($company, $validated),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function doLeaveBalance(Company $company, array $validated): JsonResponse
    {
        // #7962 — même résolution tenant-scopée que la fiche employé : une
        // seule implémentation, pas deux copies de la requête.
        $employee = $this->buildKioskEmployeeInfo->resolveEmployee($company, (string) $validated['identifier']);

        abort_if(! $employee, 404, 'EMPLOYEE_NOT_FOUND');

        $balances = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($b) => $this->buildKioskEmployeeInfo->serializeLeaveBalance($b));

        return new JsonResponse([
            'data' => [
                'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'year' => now()->year,
                'balances' => $balances,
            ],
        ]);
    }

    public function qrPunch(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'qr_data' => ['required', 'string', 'max:500'],
            'action' => ['nullable', 'in:check_in,check_out'],
            // PA2-ATT-010: QR-code kiosk punches must also feed the same
            // multi-event work_type model as mobile.
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);

        return $this->withTenantSearchPath(
            $company,
            fn (): JsonResponse => $this->doQrPunch($kiosk, $company, $validated),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function doQrPunch(AttendanceKiosk $kiosk, Company $company, array $validated): JsonResponse
    {
        // #3365 : le QR punch n'accepte QUE le jeton signé+expirant émis par
        // /me/qr-profile (OnboardingQrService, type employee_profile) — les
        // payloads JSON base64 nus (forgeables) sont rejetés.
        try {
            $qrPayload = $this->onboardingQr->decodeEmployeeProfile($validated['qr_data']);
        } catch (ValidationException) {
            return new JsonResponse([
                'error' => 'INVALID_QR_TOKEN',
                'message' => 'INVALID_QR_TOKEN',
            ], 422);
        }

        $employeeId = $qrPayload['employee']['id'] ?? null;
        $employee = $employeeId !== null
            ? Employee::query()->where('company_id', $company->id)->whereKey($employeeId)->first()
            : null;

        if (! $employee) {
            return new JsonResponse([
                'error' => 'EMPLOYEE_NOT_FOUND',
                'message' => 'EMPLOYEE_NOT_FOUND',
            ], 404);
        }

        // Le service punch résout par email/matricule/zkteco_id — on lui passe
        // l'identifiant le plus fiable de l'employé déjà résolu (scopé tenant).
        $identifier = $employee->email ?? $employee->matricule ?? (string) $employee->id;

        $allowedWorkTypes = ['normal', 'overtime', 'break', 'resume', 'mission', 'travel', 'training', 'other'];
        $qrWorkType = $qrPayload['work_type'] ?? null;
        $qrWorkType = in_array($qrWorkType, $allowedWorkTypes, true) ? $qrWorkType : null;

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: (string) $identifier,
            action: $validated['action'] ?? 'check_in',
            workType: $validated['work_type'] ?? $qrWorkType ?? 'normal',
        );

        $action = $validated['action'] ?? 'check_in';
        $statusCode = $action === 'check_in' ? 201 : 200;

        return new JsonResponse([
            'data' => [
                'employee_id' => $log->employee_id,
                'date' => $log->date?->format('Y-m-d'),
                'check_in' => optional($log->check_in)->toIso8601String(),
                'check_out' => optional($log->check_out)->toIso8601String(),
                'method' => 'qr_code',
                'work_type' => $log->work_type,
                'session_number' => $log->session_number,
                'status' => $log->status,
            ],
        ], $statusCode);
    }

    /**
     * BIO-004 (#6765) — pointage kiosque par vérification faciale 1:1.
     *
     * Identification → capture (multipart) → qualité/liveness/comparaison
     * (moteur configurable, défaut fail-closed) → événement de pointage.
     * Un échec facial ne crée jamais de présence : réponse structurée avec
     * les méthodes de repli réellement activées (BIO-006).
     */
    public function verifyFace(KioskVerifyFaceRequest $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $capture = $request->file('capture');
        if (! $capture instanceof UploadedFile) {
            abort(422, 'CAPTURE_REQUIRED');
        }

        $result = $this->faceVerification->verifyAndPunch(
            kiosk: $kiosk,
            identifier: trim((string) $request->validated('identifier')),
            capture: $capture,
            action: (string) ($request->validated('action') ?? 'check_in'),
            workType: (string) ($request->validated('work_type') ?? 'normal'),
            deviceEventId: $request->validated('device_event_id') !== null ? (string) $request->validated('device_event_id') : null,
        );

        $log = $result['log'];

        if ($result['status']->isVerified() && $log !== null) {
            $statusCode = $log->check_out !== null ? 200 : 201;

            return new JsonResponse([
                'data' => [
                    'verified' => true,
                    'employee_id' => $log->employee_id,
                    'date' => $log->date?->format('Y-m-d'),
                    'check_in' => optional($log->check_in)->toIso8601String(),
                    'check_out' => optional($log->check_out)->toIso8601String(),
                    'method' => $log->method,
                    'work_type' => $log->work_type,
                    'session_number' => $log->session_number,
                    'status' => $log->status,
                    'correlation_id' => $result['correlation_id'],
                ],
            ], $statusCode);
        }

        // Échec facial (rejet, qualité, liveness, indisponibilité, employé
        // non enrôlé) : 422/503 structuré + méthodes de repli — jamais de
        // présence créée, jamais d'absence automatique.
        $httpStatus = $result['status'] === FaceVerificationStatus::ProviderUnavailable
            ? 503
            : 422;

        return new JsonResponse([
            'error' => $result['reason_code'] ?? 'FACE_VERIFICATION_FAILED',
            'message' => $result['reason_code'] ?? 'FACE_VERIFICATION_FAILED',
            'data' => [
                'verified' => false,
                'reason_code' => $result['reason_code'],
                'correlation_id' => $result['correlation_id'],
                'fallback_methods' => $result['fallback_methods'],
            ],
        ], $httpStatus);
    }

    private function resolveAuthorizedKiosk(Request $request, string $deviceCode): AttendanceKiosk
    {
        // Issue #2689 (QA 2026-08-15) — la bascule search_path est confiée à
        // TenantManager::withinSearchPath (BOS-019/#8204) : restauration
        // garantie par try/finally, y compris sur exception/abort — jamais de
        // fuite d'état de connexion vers les requêtes suivantes du même worker.
        return $this->tenantManager->withinSearchPath(
            'shared_tenants,public',
            function () use ($request, $deviceCode): AttendanceKiosk {
                // Issue #5588 : lookup par hash déterministe (le device_code
                // n'est plus stocké en clair — AttendanceKiosk::hashDeviceCode).
                $kiosk = AttendanceKiosk::query()
                    ->where('device_code', AttendanceKiosk::hashDeviceCode($deviceCode))
                    ->firstOrFail();

                // BIO-005 (#6766) : un appareil révoqué ne peut plus pointer ni
                // synchroniser (réponse explicite, pas un 404 ambigu).
                if ($kiosk->isRevoked()) {
                    Log::channel('audit')->warning('kiosk_auth.revoked_device', [
                        'device_code' => $deviceCode,
                        'company_id' => $kiosk->company_id,
                        'ip' => $request->ip(),
                    ]);
                    abort(403, 'DEVICE_REVOKED');
                }

                if ($kiosk->company_id !== null) {
                    $kiosk->setRelation('company', PlatformCompanyLookup::findOrFail((string) $kiosk->company_id));
                }

                $token = (string) $request->header('X-Kiosk-Token', '');
                if ($token === '' || ! Hash::check($token, (string) $kiosk->sync_token_hash)) {
                    // PA2-API-005: security-relevant event, logged to the dedicated
                    // 'audit' channel so brute-force attempts against a kiosk device
                    // token are visible independently of the per-minute throttle.
                    Log::channel('audit')->warning('kiosk_auth.failed', [
                        'device_code' => $deviceCode,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    abort(401, 'INVALID_KIOSK_TOKEN');
                }

                return $kiosk;
            },
        );
    }

    /**
     * Issue #3368 — le search_path PostgreSQL doit être restauré après chaque
     * handler kiosque (pattern #2689 / TenantManager::withinTenant) : sans
     * try/finally, les workers persistants héritent du schéma du tenant
     * précédent → résolution cross-tenant sur la requête suivante.
     *
     * BOS-019 (#8204) : la bascule est déléguée à
     * `TenantManager::withinSearchPath()` — API unique de bascule brute à
     * restauration garantie. Le middleware `kiosk.search_path`
     * (`EnsureKioskSearchPathReset`) reste le filet de sécurité.
     *
     * @param  \Closure(): JsonResponse  $callback
     */
    private function withTenantSearchPath(?Company $company, \Closure $callback): JsonResponse
    {
        return $this->tenantManager->withinSearchPath(
            $this->resolveKioskSearchPath($company),
            $callback,
        );
    }

    /**
     * Chemin de recherche appliqué aux handlers kiosque.
     *
     * Tout tenant récent partage `shared_tenants` (ADR-0028, BOS-005/#8203).
     * La branche « schema » ne sert que d'éventuels tenants historiques en
     * schéma dédié — inventaire borné de l'ADR-0028, à retirer avec la
     * migration additive de nettoyage du mode mort.
     */
    private function resolveKioskSearchPath(?Company $company): string
    {
        if ($company instanceof Company
            && $company->tenancy_type === 'schema'
            && trim((string) $company->schema_name) !== ''
        ) {
            return $company->getSafeSearchPath();
        }

        return 'shared_tenants,public';
    }

    private function serializeKiosk(AttendanceKiosk $kiosk): array
    {
        return [
            'id' => $kiosk->id,
            'name' => $kiosk->name,
            'location_label' => $kiosk->location_label,
            'device_code' => $kiosk->device_code,
            'status' => $kiosk->status,
            'biometric_mode' => $kiosk->biometric_mode,
            'site_id' => $kiosk->site_id,
            'punch_methods' => $kiosk->resolvedPunchMethods(),
            'trusted_device_label' => $kiosk->trusted_device_label,
            'revoked_at' => $kiosk->revoked_at?->toIso8601String(),
        ];
    }

    // ── BIO-005 (#6766) : gestion manager du cycle de vie des kiosques ────

    public function revoke(Request $request, int $kiosk): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $kioskModel = $this->resolveManagerKiosk($kiosk);

        // #7962 — la transition d'etat et l'audit `device.revoked` vivent dans
        // l'Action ; l'idempotence (pas de double audit sur un kiosque deja
        // revoque) y est garantie et testee.
        $kioskModel = $this->revokeKiosk->execute($kioskModel, (int) $actor->id);

        return new JsonResponse(['data' => $this->serializeKiosk($kioskModel)]);
    }

    public function rotateToken(Request $request, int $kiosk): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $kioskModel = $this->resolveManagerKiosk($kiosk);

        // #7962 — generation, empreinte et audit `device.token_rotated` dans
        // l'Action. Le token en clair ne circule que par ce retour, et n'est
        // expose que dans la reponse de rotation.
        $rotation = $this->rotateKioskToken->execute($kioskModel, (int) $actor->id);

        return new JsonResponse([
            'data' => array_replace($this->serializeKiosk($rotation['kiosk']), [
                // Le token en clair n'est retourné qu'à la rotation.
                'sync_token' => $rotation['plain_token'],
            ]),
        ]);
    }

    public function config(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        // #7962 — le payload (matrice de methodes BIO-006, politique offline
        // BIO-007, etat de synchro) est construit par `BuildKioskDeviceConfig` :
        // la politique offline n'existe plus qu'a un seul endroit.
        return new JsonResponse([
            'data' => $this->buildKioskDeviceConfig->execute(BuildKioskDeviceConfig::VIEW_CONFIG, $kiosk, $deviceCode),
        ]);
    }

    /**
     * BIO-007 (#6772) — état de synchronisation du kiosque (l'appareil a été
     * authentifié par le middleware `kiosk.device`).
     *
     * #7962 — le payload (dont la politique offline, auparavant dupliquée ici)
     * est construit par `BuildKioskDeviceConfig`.
     */
    public function syncStatus(Request $request, string $deviceCode): JsonResponse
    {
        /** @var AttendanceKiosk|null $kiosk */
        $kiosk = $request->attributes->get('kiosk_device');

        if (! $kiosk instanceof AttendanceKiosk) {
            abort(401, 'INVALID_KIOSK_TOKEN');
        }

        return new JsonResponse([
            'data' => $this->buildKioskDeviceConfig->execute(BuildKioskDeviceConfig::VIEW_SYNC_STATUS, $kiosk, $deviceCode),
        ]);
    }

    private function resolveManagerKiosk(int $kioskId): AttendanceKiosk
    {
        $company = currentCompany();

        return AttendanceKiosk::query()
            ->where('company_id', $company->id)
            ->whereKey($kioskId)
            ->firstOrFail();
    }

    /**
     * PA2-KIO-004: build a compact biometric consent/enrollment status block
     * for the kiosk employee-info screen.
     *
     * Reports whether face/fingerprint biometrics are already enabled for
     * punch (with the consent timestamp collected via the mobile enrollment
     * flow), and the state of the most recent enrollment request submitted
     * from the employee's mobile app (pending manager approval, approved,
     * or rejected) so kiosk operators know why biometric punch may not be
     * available yet for a given employee.
     */
    private function nullableIsoString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
