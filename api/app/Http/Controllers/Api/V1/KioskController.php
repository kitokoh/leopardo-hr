<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use App\Services\KioskAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $company = app('current_company');
        $actor = $request->user();

        abort_unless($actor?->isManager(), 403, 'FORBIDDEN');
        $this->setTenantSearchPath($company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
            'trusted_device_label' => ['nullable', 'string', 'max:120'],
        ]);

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'location_label' => $validated['location_label'] ?? null,
            'biometric_mode' => $validated['biometric_mode'] ?? 'fingerprint',
            'trusted_device_label' => $validated['trusted_device_label'] ?? null,
            'device_code' => strtoupper(Str::random(10)),
            'sync_token_hash' => Hash::make($plainToken = Str::random(48)),
            'status' => 'active',
        ]);

        return new JsonResponse([
            'data' => $this->serializeKiosk($kiosk) + [
                'sync_token' => $plainToken,
            ],
        ], 201);
    }

    public function punch(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
        );

        return new JsonResponse([
            'data' => [
                'employee_id' => $log->employee_id,
                'date' => $log->date?->format('Y-m-d'),
                'check_in' => optional($log->check_in)->toIso8601String(),
                'check_out' => optional($log->check_out)->toIso8601String(),
                'method' => $log->method,
                'status' => $log->status,
            ],
        ], 201);
    }

    public function roster(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);
        $this->setTenantSearchPath($company);

        $items = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query
                    ->where('biometric_face_enabled', true)
                    ->orWhere('biometric_fingerprint_enabled', true);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'email' => $employee->email,
                'matricule' => $employee->matricule,
                'zkteco_id' => $employee->zkteco_id,
                'face_enabled' => $employee->biometric_face_enabled,
                'fingerprint_enabled' => $employee->biometric_fingerprint_enabled,
            ])
            ->values();

        return new JsonResponse([
            'data' => [
                'device_code' => $kiosk->device_code,
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
            'events.*.identifier' => ['required', 'string', 'max:150'],
            'events.*.action' => ['nullable', 'in:check_in,check_out'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.external_event_id' => ['nullable', 'string', 'max:100'],
            'events.*.biometric_type' => ['nullable', 'in:fingerprint,face,mixed'],
        ]);

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        app()->instance('current_company', $kiosk->company);
        $this->setTenantSearchPath($kiosk->company);

        $processed = $this->kioskAttendanceService->syncPunches($kiosk, $validated['events']);

        return new JsonResponse([
            'data' => [
                'processed_count' => count($processed),
                'processed_log_ids' => $processed,
                'last_sync_at' => optional($kiosk->fresh()->last_sync_at)->toIso8601String(),
            ],
        ]);
    }

    private function resolveAuthorizedKiosk(Request $request, string $deviceCode): AttendanceKiosk
    {
        DB::statement('SET search_path TO shared_tenants,public');

        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        $token = (string) $request->header('X-Kiosk-Token', '');
        abort_if($token === '' || ! Hash::check($token, (string) $kiosk->sync_token_hash), 401, 'INVALID_KIOSK_TOKEN');

        return $kiosk;
    }

    private function setTenantSearchPath(?Company $company): void
    {
        if (! $company) {
            DB::statement('SET search_path TO shared_tenants,public');

            return;
        }

        if ($company->tenancy_type === 'schema' && $company->schema_name) {
            DB::statement('SET search_path TO '.$company->schema_name.',public');

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
            'trusted_device_label' => $kiosk->trusted_device_label,
        ];
    }
}
