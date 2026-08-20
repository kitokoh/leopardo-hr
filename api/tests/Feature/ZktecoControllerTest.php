<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Illuminate\Support\Facades\DB;
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

    public function test_heartbeat_unknown_serial_returns_404(): void
    {
        $this->postJson('/api/v1/zkteco/heartbeat/UNKNOWN-SERIAL')
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');
    }

    public function test_sync_attendance_rejects_invalid_serial(): void
    {
        $this->postJson('/api/v1/zkteco/sync-attendance/NONEXISTENT', [
            'records' => [
                ['user_id' => '1', 'timestamp' => '2026-01-01 08:00:00'],
            ],
        ])->assertStatus(404);
    }

    public function test_register_device_returns_token_once(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [
                'serial_number' => 'SN-TOKEN-001',
                'name' => 'Porte A',
            ])
            ->assertCreated();

        $response->assertJsonStructure([
            'data' => ['id', 'serial_number', 'status'],
            'device_token',
        ]);

        // Le hash ne doit jamais être exposé.
        $response->assertJsonMissing(['sync_token_hash']);
        $this->assertNotNull($response->json('device_token'));

        $this->assertDatabaseHas('zkteco_devices', [
            'serial_number' => 'SN-TOKEN-001',
        ]);
    }

    public function test_heartbeat_requires_device_token(): void
    {
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-001',
            'name' => 'Porte B',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        // Sans token → 401, statut inchangé.
        $this->postJson('/api/v1/zkteco/heartbeat/SN-AUTH-001')
            ->assertStatus(401);

        $this->assertDatabaseHas('zkteco_devices', [
            'serial_number' => 'SN-AUTH-001',
            'status' => 'offline',
        ]);
    }

    public function test_heartbeat_rejects_wrong_device_token(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-002',
            'name' => 'Porte C',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        $this->withHeader('X-Device-Token', 'wrong-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-AUTH-002')
            ->assertStatus(401);
    }

    public function test_heartbeat_succeeds_with_valid_token(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-003',
            'name' => 'Porte D',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-AUTH-003')
            ->assertOk()
            ->assertJsonPath('data.status', 'online');

        $this->assertDatabaseHas('zkteco_devices', [
            'serial_number' => 'SN-AUTH-003',
            'status' => 'online',
        ]);
    }

    /**
     * #4787 — le handler public ne doit JAMAIS laisser le search_path pointer
     * vers un autre schéma : un worker persistant qui sert un heartbeat puis
     * une requête tenant hériterait du mauvais contexte (résolution
     * cross-tenant / 500). Vérifie la restauration après succès ET après 404.
     */
    public function test_heartbeat_restores_search_path_after_success(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-SP-001',
            'name' => 'Porte SP1',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-SP-001')
            ->assertOk();

        $this->assertSearchPathRestored(['shared_tenants', 'public']);
    }

    public function test_heartbeat_restores_search_path_after_unknown_serial(): void
    {
        $this->postJson('/api/v1/zkteco/heartbeat/SN-SP-UNKNOWN')
            ->assertStatus(404);

        $this->assertSearchPathRestored(['shared_tenants', 'public']);
    }

    public function test_heartbeat_restores_search_path_after_rejected_token(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-SP-002',
            'name' => 'Porte SP2',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        $this->withHeader('X-Device-Token', 'wrong-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-SP-002')
            ->assertStatus(401);

        $this->assertSearchPathRestored(['shared_tenants', 'public']);
    }

    public function test_sync_attendance_restores_search_path_after_success(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-SP-003',
            'name' => 'Porte SP3',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'zkteco_id' => 'zk-sp-3',
        ]);

        $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/sync-attendance/SN-SP-003', [
                'records' => [
                    ['user_id' => 'zk-sp-3', 'timestamp' => '2026-01-01 08:00:00'],
                ],
            ])
            ->assertStatus(201);

        $this->assertSearchPathRestored(['shared_tenants', 'public']);
    }

    public function test_sync_attendance_rejects_unauthenticated_device_and_writes_nothing(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-004',
            'name' => 'Porte E',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'zkteco_id' => 'zk-42',
        ]);

        // Sans token : 401 et AUCUNE écriture dans attendance_logs.
        $this->postJson('/api/v1/zkteco/sync-attendance/SN-AUTH-004', [
            'records' => [
                ['user_id' => 'zk-42', 'timestamp' => '2026-01-01 08:00:00'],
            ],
        ])->assertStatus(401);

        $this->assertDatabaseMissing('attendance_logs', [
            'method' => 'zkteco',
        ]);
    }

    public function test_sync_attendance_succeeds_with_valid_token(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-005',
            'name' => 'Porte F',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'zkteco_id' => 'zk-43',
        ]);

        $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/sync-attendance/SN-AUTH-005', [
                'records' => [
                    ['user_id' => 'zk-43', 'timestamp' => '2026-01-01 08:00:00', 'punch_type' => 0],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 1);

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'date' => '2026-01-01',
            'method' => 'zkteco',
        ]);
    }

    public function test_regenerate_token_rotates_and_revokes_old_token(): void
    {
        /** @var ZktecoDevice $device */
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-006',
            'name' => 'Porte G',
            'sync_token_hash' => bcrypt('old-device-token'),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices/'.$device->id.'/regenerate-token')
            ->assertOk();

        $newToken = $response->json('device_token');
        $this->assertNotNull($newToken);
        $this->assertNotSame('old-device-token', $newToken);

        // L'ancien token est révoqué.
        $this->withHeader('X-Device-Token', 'old-device-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-AUTH-006')
            ->assertStatus(401);

        // Le nouveau fonctionne.
        $this->withHeader('X-Device-Token', $newToken)
            ->postJson('/api/v1/zkteco/heartbeat/SN-AUTH-006')
            ->assertOk();
    }

    public function test_regenerate_token_requires_manager(): void
    {
        /** @var ZktecoDevice $device */
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-AUTH-007',
            'name' => 'Porte H',
            'sync_token_hash' => bcrypt('valid-device-token'),
        ]);

        $this->actingAs($this->employee)
            ->postJson('/api/v1/zkteco/devices/'.$device->id.'/regenerate-token')
            ->assertForbidden();
    }

    public function test_push_users_requires_manager_role(): void
    {
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-PUSH-001',
            'name' => 'Porte I',
        ]);

        $this->actingAs($this->employee)
            ->postJson('/api/v1/zkteco/devices/SN-PUSH-001/push-users')
            ->assertForbidden();
    }

    public function test_push_users_is_scoped_to_current_company(): void
    {
        // #4187 : un manager du tenant A ne doit pas pouvoir pousser des
        // utilisateurs vers un appareil du tenant B connu par serial_number
        // (lookup autrefois par serial seul → action cross-tenant).
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        $otherDevice = ZktecoDevice::query()->create([
            'company_id' => $otherCompany->id,
            'serial_number' => 'SN-PUSH-OTHER-001',
            'name' => 'Porte tenant B',
        ]);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices/'.$otherDevice->serial_number.'/push-users')
            ->assertStatus(404);

        // L'appareil du tenant courant reste joignable (pas de sur-scope).
        $ownDevice = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-PUSH-OWN-001',
            'name' => 'Porte tenant A',
        ]);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices/'.$ownDevice->serial_number.'/push-users')
            ->assertOk();
    }

    // ── #5120 — punch_methods CRUD ──────────────────────────────────────

    public function test_register_device_stores_punch_methods(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [
                'serial_number' => 'SN-PM-001',
                'name' => 'Porte PM1',
                'punch_methods' => ['face', 'card'],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.punch_methods', ['face', 'card']);

        $this->assertDatabaseHas('zkteco_devices', [
            'serial_number' => 'SN-PM-001',
        ]);
    }

    public function test_register_device_without_punch_methods_stores_null(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [
                'serial_number' => 'SN-PM-002',
                'name' => 'Porte PM2',
            ])
            ->assertCreated();

        // null = toutes méthodes autorisées (rétro-compat)
        $response->assertJsonPath('data.punch_methods', null);
    }

    public function test_register_device_rejects_invalid_punch_method(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [
                'serial_number' => 'SN-PM-003',
                'name' => 'Porte PM3',
                'punch_methods' => ['retina'],
            ])
            ->assertUnprocessable();
    }

    public function test_update_device_sets_punch_methods(): void
    {
        /** @var ZktecoDevice $device */
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-PM-004',
            'name' => 'Porte PM4',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson('/api/v1/zkteco/devices/'.$device->id, [
                'punch_methods' => ['fingerprint'],
            ])
            ->assertOk();

        $response->assertJsonPath('data.punch_methods', ['fingerprint']);
    }

    public function test_update_device_resets_punch_methods_to_null(): void
    {
        /** @var ZktecoDevice $device */
        $device = ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-PM-005',
            'name' => 'Porte PM5',
            'punch_methods' => ['face', 'card'],
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson('/api/v1/zkteco/devices/'.$device->id, [
                'punch_methods' => null,
            ])
            ->assertOk();

        $response->assertJsonPath('data.punch_methods', null);
    }

    public function test_device_punch_methods_scoped_to_tenant(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        $otherDevice = ZktecoDevice::query()->create([
            'company_id' => $otherCompany->id,
            'serial_number' => 'SN-PM-OTHER-001',
            'name' => 'Borne autre tenant',
        ]);

        $this->actingAs($this->manager)
            ->putJson('/api/v1/zkteco/devices/'.$otherDevice->id, [
                'punch_methods' => ['card'],
            ])
            ->assertStatus(404);
    }

    public function test_index_exposes_punch_methods(): void
    {
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-PM-IDX-001',
            'name' => 'Porte IDX',
            'punch_methods' => ['fingerprint', 'face'],
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/zkteco/devices')
            ->assertOk();

        $response->assertJsonFragment(['punch_methods' => ['fingerprint', 'face']]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * #4787/#4817 — vérifie que le search_path a été restauré (schémas et
     * ordre). Comparaison normalisée : PostgreSQL formate SHOW search_path
     * avec « , » (espace) après un SET alors que le défaut de connexion
     * (option DSN) s'affiche sans espace — comparer les chaînes brutes
     * casse la suite selon l'historique de SET de la session.
     *
     * @param array<int, string> $expectedSchemas
     */
    private function assertSearchPathRestored(array $expectedSchemas): void
    {
        $row = DB::selectOne('SHOW search_path');
        $this->assertNotNull($row);

        /** @var \Closure(string): list<string> $normalize */
        $normalize = static fn (string $path): array => array_values(array_filter(array_map(
            'trim',
            explode(',', str_replace('"', '', $path)),
        ), static fn (string $s): bool => $s !== ''));

        $this->assertSame($expectedSchemas, $normalize((string) $row->search_path));
    }
}
