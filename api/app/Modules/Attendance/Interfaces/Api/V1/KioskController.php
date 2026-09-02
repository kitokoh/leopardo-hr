<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;
use App\Modules\Attendance\Infrastructure\Services\KioskAttendanceService;
use App\Modules\HR\Domain\Contracts\OnboardingQrInterface;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
        private readonly OnboardingQrInterface $onboardingQr,
        private readonly BiometricAuditLogger $biometricAudit,
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
        $this->setTenantSearchPath($company);

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

        $plainDeviceCode = strtoupper(Str::random(10));

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'location_label' => $validated['location_label'] ?? null,
            'biometric_mode' => $validated['biometric_mode'] ?? 'fingerprint',
            'trusted_device_label' => $validated['trusted_device_label'] ?? null,
            'site_id' => $validated['site_id'] ?? null,
            'punch_methods' => isset($validated['punch_methods'])
                ? $this->normalizePunchMethods($validated['punch_methods'])
                : null,
            // Issue #5588 : le device_code n'est plus stocké en clair (hash
            // déterministe sha256, lookup par égalité). Le code en clair
            // n'est retourné qu'à la création (provisioning du kiosque).
            'device_code' => AttendanceKiosk::hashDeviceCode($plainDeviceCode),
            'sync_token_hash' => Hash::make($plainToken = Str::random(48)),
            'status' => 'active',
        ]);

        return new JsonResponse([
            'data' => array_replace($this->serializeKiosk($kiosk), [
                'device_code' => $plainDeviceCode,
                'sync_token' => $plainToken,
            ]),
        ], 201);
    }

    public function punch(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
            // PA2-ATT-010: kiosk punches must feed the same multi-event
            // work_type model as mobile (normal/overtime/break/resume/
            // mission/travel/training/other), not just plain in/out.
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
            // BIO-006 (#6767) : méthode réellement utilisée + validation
            // manager pour les cas exceptionnels.
            'method' => ['nullable', 'in:fingerprint,face,badge,pin,manager,card'],
            'manager_employee_id' => ['nullable', 'integer'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
            workType: $validated['work_type'] ?? 'normal',
            method: isset($validated['method']) ? (string) $validated['method'] : null,
            managerEmployeeId: isset($validated['manager_employee_id']) ? (int) $validated['manager_employee_id'] : null,
        );

        // REST convention: 201 Created for check_in, 200 OK for check_out
        $action = $validated['action'] ?? 'check_in';
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
        $this->setTenantSearchPath($company);

        $hasFaceColumn = Schema::hasColumn('employees', 'biometric_face_enabled');
        $hasFingerprintColumn = Schema::hasColumn('employees', 'biometric_fingerprint_enabled');

        $items = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->when($hasFaceColumn || $hasFingerprintColumn, function ($query) use ($hasFaceColumn, $hasFingerprintColumn): void {
                $query->where(function ($biometricQuery) use ($hasFaceColumn, $hasFingerprintColumn): void {
                    if ($hasFaceColumn) {
                        $biometricQuery->orWhere('biometric_face_enabled', true);
                    }

                    if ($hasFingerprintColumn) {
                        $biometricQuery->orWhere('biometric_fingerprint_enabled', true);
                    }
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => $this->serializeRosterEmployee($employee, $hasFaceColumn, $hasFingerprintColumn))
            ->values();

        return new JsonResponse([
            'data' => [
                'device_code' => $deviceCode,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'employees' => $items,
            ],
        ]);
    }

    public function sync(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array'],
            // Les identifiants absents ou composés d'espaces sont isolés par
            // KioskAttendanceService et retournés dans skipped[].
            'events.*.identifier' => ['nullable', 'string', 'max:150'],
            'events.*.action' => ['nullable', 'in:check_in,check_out'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.external_event_id' => ['nullable', 'string', 'max:100'],
            'events.*.biometric_type' => ['nullable', 'in:fingerprint,face,mixed'],
            // PA2-ATT-010: offline-synced kiosk events must also carry the
            // multi-event work_type, same as mobile's offline sync payloads.
            'events.*.work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        app()->instance('current_company', $kiosk->company);

        return $this->withTenantSearchPath(
            $kiosk->company,
            // #5588 (follow-up) : transmettre le device_code EN CLAIR (celui de
            // l'URL) — le modèle ne porte plus que la dérivation (64 hex).
            fn (): JsonResponse => $this->doSync($kiosk, $validated, $deviceCode),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function doSync(AttendanceKiosk $kiosk, array $validated, string $deviceCode): JsonResponse
    {
        $this->setTenantSearchPath($kiosk->company);

        $result = $this->kioskAttendanceService->syncPunches($kiosk, $validated['events'], $deviceCode);

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
        $this->setTenantSearchPath($company);

        $employee = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($validated): void {
                $query->where('matricule', $validated['identifier'])
                    ->orWhere('email', $validated['identifier'])
                    ->orWhere('zkteco_id', $validated['identifier']);
            })
            ->first();

        abort_if(! $employee, 404, 'EMPLOYEE_NOT_FOUND');

        $today = now()->toDateString();
        $todayAttendance = DB::table('attendance_logs')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        $leaveBalance = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($b) => $this->serializeLeaveBalance($b));

        return new JsonResponse([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                    'matricule' => $employee->matricule,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->name,
                    'photo_url' => $employee->photo_url ?? null,
                ],
                'today_attendance' => $todayAttendance ? [
                    'check_in' => $todayAttendance->check_in,
                    'check_out' => $todayAttendance->check_out,
                    'status' => $todayAttendance->status,
                ] : null,
                'leave_balances' => $leaveBalance,
                // PA2-KIO-004: surface the employee's mobile-submitted biometric
                // enrollment consent/status on the kiosk so field staff can see
                // at a glance whether biometric punch is enabled/pending/rejected
                // for this employee, without leaving the kiosk screen.
                'biometric_enrollment' => $this->serializeBiometricEnrollmentStatus($employee),
            ],
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
        $this->setTenantSearchPath($company);

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
        $this->setTenantSearchPath($company);

        $employee = Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($validated): void {
                $query->where('matricule', $validated['identifier'])
                    ->orWhere('email', $validated['identifier'])
                    ->orWhere('zkteco_id', $validated['identifier']);
            })
            ->firstOrFail();

        $balances = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($b) => $this->serializeLeaveBalance($b));

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
        $this->setTenantSearchPath($company);

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

    private function resolveAuthorizedKiosk(Request $request, string $deviceCode): AttendanceKiosk
    {
        // Issue #2689 (QA 2026-08-15) — le SET search_path doit être annulé
        // (try/finally) pour ne pas laisser l'état de connexion PostgreSQL
        // pointer vers shared_tenants sur les requêtes suivantes du même
        // worker (pattern RequestTrialSignup).
        // #2973 : lecture du search_path — larastan type selectOne() non-null,
        // les variantes nullsafe/?? sont refusées par PHPStan strict. Garde
        // is_object + property_exists, défaut explicite si indisponible.
        $previous = 'public,shared_tenants';
        try {
            $searchPathRow = DB::selectOne('SHOW search_path');
            if (is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')) {
                $previous = (string) $searchPathRow->search_path;
            }
        } catch (Throwable) {
            // défaut conservé
        }
        DB::statement('SET search_path TO shared_tenants,public');

        try {
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
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }

    /**
     * Issue #3368 — le search_path PostgreSQL doit être restauré après chaque
     * handler kiosque (pattern #2689 / TenantManager::withinTenant) : sans
     * try/finally, les workers persistants héritent du schéma du tenant
     * précédent → résolution cross-tenant sur la requête suivante.
     *
     * @param  \Closure(): JsonResponse  $callback
     */
    private function withTenantSearchPath(?Company $company, \Closure $callback): JsonResponse
    {
        $searchPathRow = DB::selectOne('SHOW search_path');
        $previous = is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')
            ? (string) $searchPathRow->search_path
            : 'public';
        $this->setTenantSearchPath($company);

        try {
            return $callback();
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }

    private function setTenantSearchPath(?Company $company): void
    {
        if (! $company) {
            DB::statement('SET search_path TO shared_tenants,public');

            return;
        }

        if ($company->tenancy_type === 'schema' && $company->schema_name) {
            DB::statement('SET search_path TO '.$company->getSafeSearchPath());

            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
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

        if (! $kioskModel->isRevoked()) {
            $kioskModel->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by_employee_id' => $actor->id,
            ])->save();

            $this->biometricAudit->log(
                companyId: (string) $kioskModel->company_id,
                event: 'device.revoked',
                kioskId: (int) $kioskModel->id,
                siteId: $kioskModel->site_id !== null ? (int) $kioskModel->site_id : null,
                actorEmployeeId: (int) $actor->id,
                correlationId: (string) $kioskModel->device_code,
                deviceCodeHash: (string) $kioskModel->device_code,
            );
        }

        return new JsonResponse(['data' => $this->serializeKiosk($kioskModel->fresh() ?? $kioskModel)]);
    }

    public function rotateToken(Request $request, int $kiosk): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $kioskModel = $this->resolveManagerKiosk($kiosk);

        $plainToken = Str::random(48);
        $kioskModel->forceFill([
            'sync_token_hash' => Hash::make($plainToken),
        ])->save();

        $this->biometricAudit->log(
            companyId: (string) $kioskModel->company_id,
            event: 'device.token_rotated',
            kioskId: (int) $kioskModel->id,
            siteId: $kioskModel->site_id !== null ? (int) $kioskModel->site_id : null,
            actorEmployeeId: (int) $actor->id,
            deviceCodeHash: (string) $kioskModel->device_code,
        );

        return new JsonResponse([
            'data' => array_replace($this->serializeKiosk($kioskModel), [
                // Le token en clair n'est retourné qu'à la rotation.
                'sync_token' => $plainToken,
            ]),
        ]);
    }

    public function config(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        return new JsonResponse([
            'data' => [
                'device_code' => $deviceCode,
                'company_id' => $kiosk->company_id,
                'name' => $kiosk->name,
                'status' => $kiosk->status,
                'biometric_mode' => $kiosk->biometric_mode,
                'site_id' => $kiosk->site_id,
                // BIO-006 (#6767) : matrice serveur — l'interface ne propose
                // que les méthodes réellement activées (BIO-009 #6774).
                'punch_methods' => $kiosk->resolvedPunchMethods(),
                'server_time' => now()->toIso8601String(),
            ],
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
     * Normalise la matrice envoyée au provisioning (BIO-006) : `card`
     * (vocabulaire ZKTeco) est stocké `badge` (domaine ATT-002).
     *
     * @param  array<array-key, mixed>  $methods
     * @return list<string>
     */
    private function normalizePunchMethods(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }
            $normalized[] = $method === 'card' ? 'badge' : $method;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $method): bool => in_array($method, AttendanceKiosk::KIOSK_PUNCH_METHODS_ALL, true)
        )));
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
    /**
     * @return array{face_enabled: bool, fingerprint_enabled: bool, consented_at: string|null, pending_request: bool, latest_request_status: string|null, latest_request_submitted_at: string|null}
     */
    private function serializeBiometricEnrollmentStatus(Employee $employee): array
    {
        $hasFaceColumn = Schema::hasColumn('employees', 'biometric_face_enabled');
        $hasFingerprintColumn = Schema::hasColumn('employees', 'biometric_fingerprint_enabled');

        $faceEnabled = $hasFaceColumn ? (bool) ($employee->biometric_face_enabled ?? false) : false;
        $fingerprintEnabled = $hasFingerprintColumn ? (bool) ($employee->biometric_fingerprint_enabled ?? false) : false;

        $latestRequest = null;
        if (Schema::hasTable('biometric_enrollment_requests')) {
            $latestRequest = BiometricEnrollmentRequest::query()
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->first();
        }

        return [
            'face_enabled' => $faceEnabled,
            'fingerprint_enabled' => $fingerprintEnabled,
            'consented_at' => $this->nullableIsoString($employee->biometric_consent_at ?? null),
            'pending_request' => $latestRequest && $latestRequest->status === 'pending',
            'latest_request_status' => $latestRequest?->status,
            'latest_request_submitted_at' => $latestRequest ? $this->nullableIsoString($latestRequest->submitted_at) : null,
        ];
    }

    private function serializeLeaveBalance(object $balance): array
    {
        $remaining = (float) ($balance->remaining ?? $balance->balance ?? 0);
        $used = (float) ($balance->used_days ?? $balance->used ?? 0);
        $pending = (float) ($balance->pending ?? 0);
        $total = (float) ($balance->total_days ?? $balance->entitled_days ?? ($remaining + $used + $pending));

        return [
            'leave_type' => (string) ($balance->leave_type ?? $balance->absence_type_id ?? 'annual'),
            'total' => $total,
            'used' => $used,
            'pending' => $pending,
            'remaining' => $remaining,
        ];
    }

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

    private function serializeRosterEmployee(Employee $employee, bool $hasFaceColumn, bool $hasFingerprintColumn): array
    {
        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'email' => $employee->email,
            'matricule' => $employee->matricule,
            'zkteco_id' => $employee->zkteco_id,
            'face_enabled' => $hasFaceColumn ? (bool) ($employee->biometric_face_enabled ?? false) : false,
            'fingerprint_enabled' => $hasFingerprintColumn ? (bool) ($employee->biometric_fingerprint_enabled ?? false) : false,
        ];
    }
}
