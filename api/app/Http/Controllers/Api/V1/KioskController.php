<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use App\Models\KioskAnnouncement;
use App\Services\KioskAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Requests\Api\V1\Kiosk\EmployeeInfoKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\LeaveBalanceKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\PunchKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\QrPunchKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\RegisterKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\SyncKioskRequest;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {}

    public function register(RegisterKioskRequest $request): JsonResponse
    {
        $company = currentCompany();
        /** @var Employee $actor */
        $actor = $request->user();

        abort_unless($actor?->isManager(), 403, 'FORBIDDEN');
        $this->setTenantSearchPath($company);

        $validated = $request->validated();

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

    public function punch(PunchKioskRequest $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);

        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
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
                'status' => $log->status,
            ],
        ], $statusCode);
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

    public function sync(SyncKioskRequest $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        app()->instance('current_company', $kiosk->company);
        $this->setTenantSearchPath($kiosk->company);

        $processed = $this->kioskAttendanceService->syncPunches($kiosk, $validated['events']);

        return new JsonResponse([
            'data' => [
                'processed_count' => count($processed),
                'processed_log_ids' => $processed,
                'last_sync_at' => $kiosk->fresh()?->last_sync_at?->toIso8601String(),
            ],
        ]);
    }

    public function employeeInfo(EmployeeInfoKioskRequest $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);
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
            ],
        ]);
    }

    public function announcements(Request $request, string $deviceCode): JsonResponse
    {
        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        $this->setTenantSearchPath($company);

        $announcements = KioskAnnouncement::query()
            ->where('company_id', $company->id)
            ->active()
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'priority' => $a->priority,
                'starts_at' => $a->starts_at?->toIso8601String(),
                'expires_at' => $a->expires_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $announcements]);
    }

    public function leaveBalance(LeaveBalanceKioskRequest $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);
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

    public function qrPunch(QrPunchKioskRequest $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = $this->resolveAuthorizedKiosk($request, $deviceCode);
        $company = $kiosk->company;
        app()->instance('current_company', $company);
        $this->setTenantSearchPath($company);

        $qrPayload = json_decode(base64_decode($validated['qr_data'], true), true);
        $identifier = $qrPayload['employee_id'] ?? $qrPayload['matricule'] ?? $validated['qr_data'];

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: (string) $identifier,
            action: $validated['action'] ?? 'check_in',
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
                'status' => $log->status,
            ],
        ], $statusCode);
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
            'trusted_device_label' => $kiosk->trusted_device_label,
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
}
