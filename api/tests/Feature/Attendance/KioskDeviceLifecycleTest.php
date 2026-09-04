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
 * BIO-005 (#6766) — enregistrement et authentification des kiosques.
 *
 * Identité cryptographique révocable : site fixé au provisioning, révocation
 * (un appareil révoqué ne peut plus pointer ni synchroniser), rotation de
 * secret, usurpation refusée, rejeu borné.
 */
final class KioskDeviceLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_register_binds_site_and_method_matrix(): void
    {
        [$manager, , $company] = $this->seedScenario('site-a');
        $siteId = $this->makeSite($company);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree A',
                'biometric_mode' => 'face',
                'site_id' => $siteId,
                'punch_methods' => ['face', 'badge'],
            ])
            ->assertCreated();

        $this->assertSame('face', $response->json('data.biometric_mode'));
        $this->assertSame($siteId, $response->json('data.site_id'));
        $this->assertSame(['face', 'badge'], $response->json('data.punch_methods'));
    }

    public function test_revoked_kiosk_cannot_punch_nor_sync(): void
    {
        [$manager, $employee] = $this->seedScenario();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        // Pointe avant révocation.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertCreated();

        // Révocation par le manager (lecture dans le schéma tenant).
        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = AttendanceKiosk::query()->firstOrFail()->id;
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/kiosks/{$kioskId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        // Punch refusé (403 DEVICE_REVOKED explicite).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        // Sync refusée (événement valide mais appareil révoqué).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [[
                    'identifier' => 'FP-a',
                    'action' => 'check_in',
                    'external_event_id' => 'evt-revoked-1',
                ]],
            ])
            ->assertStatus(403);
    }

    public function test_rotate_token_invalidates_the_old_secret(): void
    {
        [$manager, $employee] = $this->seedScenario();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = AttendanceKiosk::query()->firstOrFail()->id;
        $rotated = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/kiosks/{$kioskId}/rotate-token")
            ->assertOk();
        $newToken = $rotated->json('data.sync_token');
        $this->assertIsString($newToken);
        $this->assertNotSame($syncToken, $newToken);

        // Ancien token → 401.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertStatus(401);

        // Nouveau token → OK.
        $this->withHeader('X-Kiosk-Token', $newToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertCreated();
    }

    public function test_impersonation_and_usurpation_are_rejected(): void
    {
        [$manager, $employee] = $this->seedScenario();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        // Token invalide → 401 (usurpation).
        $this->withHeader('X-Kiosk-Token', 'wrong-token')
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertStatus(401);

        // Device_code inconnu (autre tenant ou inventé) → 404, pas d'indice.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.strtoupper(Str::random(10)).'/punch', ['identifier' => 'FP-a'])
            ->assertStatus(404);

        // Un kiosque ne peut pas pointer un employé d'un autre tenant : le
        // device_code est lié au tenant au provisioning (hash).
        [, , $companyB] = $this->seedScenario('tenant-b');
        $foreignEmployee = $this->makeEmployee($companyB, 'eve@b.test', 'employee');
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => (string) $foreignEmployee->email])
            ->assertStatus(404);
    }

    public function test_double_check_in_replay_is_rejected(): void
    {
        [$manager, $employee] = $this->seedScenario();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertCreated();

        // Rejeu : session déjà ouverte → 422 (aucun doublon de présence).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', ['identifier' => 'FP-a'])
            ->assertStatus(422);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertSame(
            1,
            DB::table('attendance_logs')->where('employee_id', $employee->id)->count()
        );
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Company} [manager, employee, company]
     */
    protected function seedScenario(string $slugSuffix = 'a'): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slugSuffix,
            'slug' => 'company-kiosk-'.$slugSuffix.'-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slugSuffix.'@kiosk-life.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = $this->makeEmployee($company, $slugSuffix.'-karim@kiosk-life.test', 'employee');
        $employee->forceFill([
            'matricule' => 'EMP-'.$slugSuffix,
            'zkteco_id' => 'FP-'.$slugSuffix,
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-'.$slugSuffix,
        ])->save();

        $manager = $this->makeEmployee($company, $slugSuffix.'-manager@kiosk-life.test', 'manager');
        $manager->forceFill(['manager_role' => 'principal'])->save();

        return [$manager, $employee, $company];
    }

    private function makeEmployee(Company $company, string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Kiosk',
            'email' => $email,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function makeSite(Company $company): int
    {
        DB::statement('SET search_path TO shared_tenants,public');
        $siteId = DB::table('sites')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Site Alger Centre',
        ]);

        return (int) $siteId;
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(Employee $manager): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }
}
