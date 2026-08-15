<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2232 — endpoints d'écriture sensibles sans validation
 * (CalendarSync sync/disconnect, EdgeNode forceSync/revokeNode,
 * Zkteco destroy) : les FormRequests renvoient 422 sur entrée invalide
 * au lieu de laisser passer des données malformées.
 */
class WriteFormRequestValidationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_calendar_disconnect_rejects_unknown_provider(): void
    {
        [$company, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/calendar/disconnect/skype')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);

        // Les providers valides passent la validation (aucun changement de
        // comportement — pas de connexion en base → message de succès).
        $this->deleteJson('/api/v1/calendar/disconnect/google')
            ->assertOk();
    }

    public function test_calendar_sync_rejects_invalid_provider(): void
    {
        [$company, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/calendar/sync', ['provider' => 'skype'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);

        // Payload sans provider (usage normal) inchangé.
        $this->postJson('/api/v1/calendar/sync', [])->assertOk();
    }

    public function test_edge_force_sync_rejects_non_uuid_node_id(): void
    {
        $this->superAdminAuth();

        $this->postJson('/api/v1/platform/edge/nodes/not-a-uuid/sync')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);

        // Alias admin SPA : même garde.
        $this->postJson('/api/v1/admin/edge-nodes/not-a-uuid/sync')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);
    }

    public function test_edge_revoke_rejects_non_uuid_node_id(): void
    {
        $this->superAdminAuth();

        $this->deleteJson('/api/v1/platform/edge/nodes/not-a-uuid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);

        $this->postJson('/api/v1/admin/edge-nodes/not-a-uuid/revoke')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);
    }

    public function test_zkteco_destroy_rejects_zero_id(): void
    {
        [$company, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/zkteco/devices/0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_zkteco_destroy_still_works_with_valid_id(): void
    {
        [$company, $manager] = $this->actors();

        $device = ZktecoDevice::query()->create([
            'company_id' => $company->id,
            'serial_number' => 'ZK-2026-0001',
            'name' => 'Pointeuse entrée',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
            'protocol' => 'tcp',
            'status' => 'online',
        ]);

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/zkteco/devices/'.$device->id)->assertStatus(204);
        $this->assertDatabaseMissing('zkteco_devices', ['id' => $device->id]);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function actors(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => 'manager@write-validation.test',
        ]);

        return [$company, $manager];
    }

    private function superAdminAuth(): void
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform@write-validation.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');
    }
}
