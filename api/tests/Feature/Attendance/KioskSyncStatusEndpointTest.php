<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QLT-001 (#6775) — endpoint d'état de synchronisation du kiosque
 * (BIO-007 #6772) : statut appareil, compteur acquitté, heure serveur et
 * politique offline publiée ; appareil inconnu → 401 ; appareil révoqué →
 * 403 DEVICE_REVOKED.
 */
final class KioskSyncStatusEndpointTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company SyncStatus',
            'slug' => 'company-sync-status-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@qlt-sync-status.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->manager = $this->makeEmployee('manager@qlt-sync-status.test', 'manager');
        $this->manager->forceFill(['manager_role' => 'principal'])->save();
    }

    public function test_sync_status_exposes_status_counter_server_time_and_offline_policy(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/sync-status')
            ->assertOk()
            ->assertJsonPath('data.device_code', $deviceCode)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.acked_event_counter', 0)
            ->assertJsonPath('data.offline_policy.max_age_days', (int) config('attendance.kiosk.offline.max_age_days', 14))
            ->assertJsonPath('data.offline_policy.max_events_per_batch', (int) config('attendance.kiosk.offline.max_events_per_batch', 500));

        // `server_time` est un ISO8601 exploitable par le kiosque.
        $serverTime = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/sync-status')
            ->assertOk()
            ->json('data.server_time');
        $this->assertIsString($serverTime);
        $this->assertNotNull(strtotime($serverTime), 'server_time doit être une date exploitable');
    }

    public function test_unknown_device_gets_401(): void
    {
        [$deviceCode] = $this->registerKiosk();

        // Device_code inconnu (même avec un token de forme valide) → 401.
        $unknownCode = strtoupper(Str::random(10));
        $this->assertNotSame($unknownCode, $deviceCode);

        $this->withHeader('X-Kiosk-Token', Str::random(48))
            ->getJson('/api/v1/kiosks/'.$unknownCode.'/sync-status')
            ->assertStatus(401)
            ->assertJsonPath('error', 'INVALID_KIOSK_TOKEN');
    }

    public function test_revoked_device_gets_403_device_revoked(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk();

        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = AttendanceKiosk::query()->where('company_id', $this->company->id)->firstOrFail()->id;
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/kiosks/{$kioskId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        // Le middleware `kiosk.device` refuse l'appareil révoqué AVANT la
        // vérification du token (réponse explicite, pas un 404 ambigu).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/sync-status')
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');
    }

    private function makeEmployee(string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'SyncStatus',
            'email' => $email,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $this->company->id,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(): array
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree SyncStatus',
                'biometric_mode' => 'fingerprint',
                'punch_methods' => ['fingerprint', 'badge'],
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }
}
