<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #5121 — Tests Feature : enforcement des méthodes de pointage autorisées
 * lors de la synchronisation attendance (POST /zkteco/sync-attendance/{sn}).
 *
 * Cas couverts :
 *  - Méthode non autorisée par le device → refus (PUNCH_METHOD_NOT_ALLOWED)
 *  - Méthode autorisée, employé non enrôlé → refus (EMPLOYEE_METHOD_NOT_ENROLLED)
 *  - Rétro-compat : payload sans `method` → fingerprint (comportement actuel)
 *  - Carte : badge_number résout l'employé + enrôlement
 */
class ZktecoSyncMethodEnforcementTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private ZktecoDevice $device;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createDevice(array $extra = []): ZktecoDevice
    {
        return ZktecoDevice::query()->create(array_merge([
            'company_id' => $this->company->id,
            'serial_number' => 'SN-ENF-'.uniqid(),
            'name' => 'Borne test enforcement',
            'sync_token_hash' => bcrypt('device-token-enf'),
        ], $extra));
    }

    /** @param array<string, mixed> $extra */
    private function createEmployee(array $extra = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'zkteco_id' => 'zk-enf-'.uniqid(),
            'biometric_fingerprint_enabled' => true,
            'biometric_face_enabled' => false,
        ], $extra));
    }

    private function syncRequest(ZktecoDevice $device, array $records): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Device-Token', 'device-token-enf')
            ->postJson('/api/v1/zkteco/sync-attendance/'.$device->serial_number, [
                'records' => $records,
            ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    /**
     * Rétro-compat : un enregistrement sans `method` est traité comme
     * fingerprint. Si le device autorise toutes les méthodes (null) et
     * l'employé a fingerprint actif, le pointage est accepté.
     */
    public function test_sync_without_method_defaults_to_fingerprint(): void
    {
        $device = $this->createDevice(['punch_methods' => null]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => true,
        ]);

        $this->syncRequest($device, [
            ['user_id' => $employee->zkteco_id, 'timestamp' => '2026-09-01 08:00:00'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 1)
            ->assertJsonPath('data.errors', 0);
    }

    /**
     * #5121 — device autorisant uniquement `face` et `card`.
     * Un pointage avec `method: fingerprint` est refusé.
     */
    public function test_sync_rejects_method_not_allowed_by_device(): void
    {
        $device = $this->createDevice(['punch_methods' => ['face', 'card']]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => true,
        ]);

        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'fingerprint',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 0)
            ->assertJsonPath('data.errors', 1);
    }

    /**
     * #5121 — device autorisant `fingerprint`, employé non enrôlé.
     * Le refus se fait via EMPLOYEE_METHOD_NOT_ENROLLED.
     */
    public function test_sync_rejects_employee_not_enrolled_for_method(): void
    {
        $device = $this->createDevice(['punch_methods' => ['fingerprint']]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
        ]);

        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'fingerprint',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 0)
            ->assertJsonPath('data.errors', 1);
    }

    /**
     * #5121 — device autorise `fingerprint`, employé enrôlé.
     * Le pointage passe normalement.
     */
    public function test_sync_accepts_enrolled_employee_for_allowed_method(): void
    {
        $device = $this->createDevice(['punch_methods' => ['fingerprint']]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => true,
        ]);

        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'fingerprint',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 1)
            ->assertJsonPath('data.errors', 0);
    }

    /**
     * #5121/#5122 — flux carte : badge_number résout l'employé,
     * le pointage est accepté si `card` est autorisé par le device.
     */
    public function test_sync_card_method_resolves_by_badge_number(): void
    {
        $device = $this->createDevice(['punch_methods' => ['card']]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
            'badge_number' => 'BADGE-001',
        ]);

        // Le user_id ici est ignoré; on identifie l'employé via badge_number dans le record.
        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'card',
                'badge_number' => 'BADGE-001',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 1)
            ->assertJsonPath('data.errors', 0);
    }

    /**
     * #5121/#5122 — flux carte refusé si l'employé n'a pas de badge_number.
     */
    public function test_sync_card_method_rejects_employee_without_badge(): void
    {
        $device = $this->createDevice(['punch_methods' => ['card']]);
        $employee = $this->createEmployee([
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
            'badge_number' => null,
        ]);

        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'card',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 0)
            ->assertJsonPath('data.errors', 1);
    }

    /**
     * #5120 — device sans restriction (punch_methods null) = toutes méthodes.
     * Rétro-compat : un face sync fonctionne si l'employé est enrôlé.
     */
    public function test_sync_unrestricted_device_accepts_any_method(): void
    {
        $device = $this->createDevice(['punch_methods' => null]);
        $employee = $this->createEmployee([
            'biometric_face_enabled' => true,
            'biometric_fingerprint_enabled' => false,
        ]);

        $this->syncRequest($device, [
            [
                'user_id' => $employee->zkteco_id,
                'timestamp' => '2026-09-01 08:00:00',
                'method' => 'face',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.records_processed', 1)
            ->assertJsonPath('data.errors', 0);
    }
}
