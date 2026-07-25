<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-ATT-010 — Kiosk synchronise avec multi-evenements.
 *
 * The kiosk punch surface (direct punch, offline sync, QR punch) must feed
 * the same multi-event work_type model already used by the mobile app
 * (normal/overtime/break/resume/mission/travel/training/other), instead of
 * being limited to a hardcoded "normal" check_in/check_out.
 */
class KioskMultiEventPunchTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_kiosk_punch_records_the_requested_work_type(): void
    {
        [$manager, $employee] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'work_type' => 'mission',
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_type', 'mission');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'work_type' => 'mission',
        ]);
    }

    public function test_kiosk_punch_defaults_to_normal_work_type_when_omitted(): void
    {
        [$manager] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_type', 'normal');
    }

    public function test_kiosk_punch_rejects_an_invalid_work_type(): void
    {
        [$manager] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'work_type' => 'not-a-real-type',
            ])
            ->assertStatus(422);
    }

    public function test_kiosk_offline_sync_preserves_work_type_per_event(): void
    {
        [$manager, $employee] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-mission-001',
                        'biometric_type' => 'fingerprint',
                        'work_type' => 'mission',
                    ],
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_out',
                        'occurred_at' => '2026-04-19T17:00:00Z',
                        'external_event_id' => 'evt-mission-002',
                        'biometric_type' => 'fingerprint',
                        'work_type' => 'mission',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 2);

        // Note: check_in and check_out for the same session share a single
        // attendance_logs row; external_event_id is updated to the closing
        // event's id on check_out. Assert the merged row carries work_type.
        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'external_event_id' => 'evt-mission-002',
            'work_type' => 'mission',
        ]);
    }

    public function test_kiosk_offline_sync_writes_an_audit_log_row_per_punch(): void
    {
        // PA2-ATT-001 - the multi-event punch model must be auditable on
        // every entry point, including the offline kiosk sync path
        // (AttendanceService::importExternalPunch()), which previously
        // never dispatched AttendanceCheckedIn/AttendanceCheckedOut and was
        // therefore invisible to the existing AuditLogger listener that
        // already covers the direct mobile check-in/check-out endpoints.
        [$manager, $employee] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-audit-001',
                        'biometric_type' => 'fingerprint',
                        'work_type' => 'mission',
                    ],
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_out',
                        'occurred_at' => '2026-04-19T17:00:00Z',
                        'external_event_id' => 'evt-audit-002',
                        'biometric_type' => 'fingerprint',
                        'work_type' => 'mission',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 2);

        DB::statement('SET search_path TO shared_tenants,public');

        $log = AttendanceLog::query()->where('employee_id', $employee->id)->firstOrFail();

        // The check-in creates the row ("created"), the check-out later
        // updates the same row ("updated") — one audit_logs row per event,
        // matching the direct mobile check-in/check-out behaviour.
        $this->assertSame(
            2,
            AuditLog::query()->where('auditable_id', $log->id)->count(),
            'Expected one audit_logs row for the offline check-in and one for the offline check-out.'
        );
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $log->id,
            'action' => 'checked_in',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $log->id,
            'action' => 'checked_out',
        ]);
    }

    public function test_kiosk_qr_punch_records_the_requested_work_type(): void
    {
        [$manager, $employee] = $this->seedCompanyManagerAndBiometricEmployee();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $qrData = base64_encode(json_encode(['matricule' => 'EMP-001']));

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/qr-punch', [
                'qr_data' => $qrData,
                'action' => 'check_in',
                'work_type' => 'travel',
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_type', 'travel');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'work_type' => 'travel',
        ]);
    }

    /**
     * @return array{0: Employee, 1: Employee} [manager, employee]
     */
    private function seedCompanyManagerAndBiometricEmployee(): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-multi.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@kiosk-multi.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@kiosk-multi.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO public');

        return [$manager, $employee];
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(Employee $manager): array
    {
        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        return [
            $kioskResponse->json('data.device_code'),
            $kioskResponse->json('data.sync_token'),
        ];
    }
}
