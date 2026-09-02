<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AttendanceRecorded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ATT-003 (#6768) — AttendanceRecorded.v1 publié à chaque événement de
 * présence, quel que soit le flux (mobile / kiosque offline), avec un payload
 * versionné et corrélé. Payroll reste découplé (projection AttendanceLog).
 */
final class AttendanceRecordedEventTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_check_in_and_check_out_dispatch_versioned_event(): void
    {
        [$manager, $employee] = $this->seedScenario();

        Event::fake([AttendanceRecorded::class]);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/attendance/check-out')
            ->assertOk();

        Event::assertDispatched(AttendanceRecorded::class, function (AttendanceRecorded $event): bool {
            return $event->eventType === 'check_in'
                && $event->employeeId > 0
                && $event->companyId !== ''
                && $event->occurredAtUtc !== ''
                && $event->correlationId !== ''
                && $event->verificationMethod === 'mobile'
                && $event->attendanceLogId > 0;
        });

        Event::assertDispatched(AttendanceRecorded::class, function (AttendanceRecorded $event): bool {
            return $event->eventType === 'check_out' && $event->attendanceLogId > 0;
        });
    }

    public function test_offline_kiosk_sync_dispatches_event_with_external_correlation(): void
    {
        [$manager, $employee] = $this->seedScenario();

        $kiosk = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', ['name' => 'Entree', 'biometric_mode' => 'fingerprint'])
            ->assertCreated();
        $deviceCode = $kiosk->json('data.device_code');
        $syncToken = $kiosk->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        Event::fake([AttendanceRecorded::class]);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => '2026-04-19T08:00:00Z',
                    'external_event_id' => 'evt-recorded-001',
                ]],
            ])
            ->assertOk();

        Event::assertDispatched(AttendanceRecorded::class, function (AttendanceRecorded $event): bool {
            return $event->eventType === 'check_in'
                && $event->correlationId === 'evt-recorded-001'
                && $event->verificationMethod === 'biometric';
        });
    }

    public function test_event_carries_version_contract(): void
    {
        $this->assertSame('1', AttendanceRecorded::VERSION);
    }

    /**
     * @return array{0: Employee, 1: Employee} [manager, employee]
     */
    protected function seedScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Events',
            'slug' => 'company-events-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@att-recorded.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Events',
            'email' => 'karim@att-recorded.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Events',
            'email' => 'manager@att-recorded.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return [$manager, $employee];
    }
}
