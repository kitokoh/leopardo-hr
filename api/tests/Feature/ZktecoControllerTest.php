<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ZktecoControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_devices_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/zkteco/devices')
            ->assertUnauthorized();
    }

    public function test_devices_list_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/zkteco/devices')
            ->assertForbidden();
    }

    public function test_register_device_validates_required_fields(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [])
            ->assertUnprocessable();
    }

    public function test_heartbeat_accepts_valid_serial(): void
    {
        // Issue #2216 : sans token valide → 401 (même pour un serial inconnu,
        // le 401 prime sur le 404 pour ne pas révéler l'existence du device).
        $this->postJson('/api/v1/zkteco/heartbeat/UNKNOWN-SERIAL')
            ->assertStatus(401);
    }

    public function test_sync_attendance_rejects_invalid_serial(): void
    {
        // Issue #2216 : sans token valide → 401.
        $this->postJson('/api/v1/zkteco/sync-attendance/NONEXISTENT', [
            'records' => [
                ['user_id' => '1', 'timestamp' => '2026-01-01 08:00:00'],
            ],
        ])->assertStatus(401);
    }

    public function test_heartbeat_requires_valid_device_token(): void
    {
        $device = \App\Modules\Attendance\Domain\Models\ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SERIAL-TOKEN-001',
            'name' => 'Pointage test',
            'sync_token_hash' => bcrypt('correct-token'),
        ]);

        // Sans en-tête → 401
        $this->postJson('/api/v1/zkteco/heartbeat/SERIAL-TOKEN-001')
            ->assertStatus(401);

        // Mauvais token → 401
        $this->postJson('/api/v1/zkteco/heartbeat/SERIAL-TOKEN-001', [], [
            'X-Device-Token' => 'wrong-token',
        ])->assertStatus(401);

        // Token valide → 200 et device marqué online
        $this->postJson('/api/v1/zkteco/heartbeat/SERIAL-TOKEN-001', [], [
            'X-Device-Token' => 'correct-token',
        ])->assertOk()
            ->assertJsonPath('data.status', 'online');

        $device->refresh();
        $this->assertSame('online', $device->status);
        $this->assertNotNull($device->last_heartbeat_at);
    }

    public function test_sync_attendance_requires_valid_device_token_and_does_not_create_records_without_it(): void
    {
        $device = \App\Modules\Attendance\Domain\Models\ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SERIAL-SYNC-001',
            'name' => 'Pointage test',
            'sync_token_hash' => bcrypt('correct-token'),
        ]);

        $payload = [
            'records' => [
                ['user_id' => '1', 'timestamp' => '2026-01-01 08:00:00'],
                ['user_id' => '2', 'timestamp' => '2026-01-01 09:00:00'],
            ],
        ];

        // Non-régression #2216 : un sync NON authentifié ne crée AUCUNE
        // entrée attendance_logs (ni ZktecoSyncLog).
        $this->postJson('/api/v1/zkteco/sync-attendance/SERIAL-SYNC-001', $payload)
            ->assertStatus(401);
        $this->assertSame(0, \App\Modules\Attendance\Domain\Models\AttendanceLog::count());
        $this->assertSame(0, \App\Modules\Attendance\Domain\Models\ZktecoSyncLog::count());

        // Mauvais token → 401, toujours aucune entrée.
        $this->postJson('/api/v1/zkteco/sync-attendance/SERIAL-SYNC-001', $payload, [
            'X-Device-Token' => 'wrong-token',
        ])->assertStatus(401);
        $this->assertSame(0, \App\Modules\Attendance\Domain\Models\AttendanceLog::count());

        // Token valide → 201 (comportement historique conservé).
        $this->postJson('/api/v1/zkteco/sync-attendance/SERIAL-SYNC-001', $payload, [
            'X-Device-Token' => 'correct-token',
        ])->assertStatus(201);
    }

    public function test_device_creation_returns_plain_token_once_and_hides_hash(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [
                'serial_number' => 'SERIAL-NEW-001',
                'name' => 'Nouveau pointage',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.sync_token_hash', null) // hash jamais exposé
            ->assertJsonStructure(['data' => ['sync_token']]);

        $response = $this->postJson('/api/v1/zkteco/devices', [
            'serial_number' => 'SERIAL-NEW-001',
            'name' => 'Nouveau pointage',
        ])->assertStatus(201);

        $plainToken = $response->json('data.sync_token');
        $this->assertIsString($plainToken);
        $this->assertGreaterThan(20, strlen($plainToken));

        /** @var \App\Modules\Attendance\Domain\Models\ZktecoDevice $device */
        $device = \App\Modules\Attendance\Domain\Models\ZktecoDevice::query()
            ->where('serial_number', 'SERIAL-NEW-001')
            ->firstOrFail();

        // Le hash est stocké, pas le token brut.
        $this->assertNotNull($device->sync_token_hash);
        $this->assertNotSame($plainToken, $device->sync_token_hash);
        $this->assertTrue(password_verify($plainToken, $device->sync_token_hash));

        // Le token retourné par la création fonctionne pour heartbeat.
        $this->postJson('/api/v1/zkteco/heartbeat/SERIAL-NEW-001', [], [
            'X-Device-Token' => $plainToken,
        ])->assertOk();

        // Le hash n'apparaît pas dans show().
        $this->actingAs($this->manager)
            ->getJson("/api/v1/zkteco/devices/{$device->id}")
            ->assertOk()
            ->assertJsonMissing(['sync_token_hash']);
    }
}

