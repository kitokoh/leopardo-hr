<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BIO-006 (#6767) — fallback & matrice de méthodes du pointage kiosque.
 *
 * Une méthode désactivée est refusée côté serveur même si l'interface
 * l'envoie ; la méthode réellement utilisée est persistée ; la validation
 * manager est contrôlée (manager actif du même tenant).
 */
final class KioskPunchMethodTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_disabled_method_is_rejected_server_side(): void
    {
        [$manager, $employee] = $this->seedScenario();
        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree',
                'biometric_mode' => 'fingerprint',
                // Seuls badge + pin sont activés sur CE kiosque.
                'punch_methods' => ['badge', 'pin'],
            ])
            ->assertCreated();
        $deviceCode = $kioskResponse->json('data.device_code');
        $this->assertIsString($deviceCode);
        $syncToken = $kioskResponse->json('data.sync_token');
        $this->assertIsString($syncToken);

        // L'interface envoie `face` (non activé) → refus serveur, pas de log.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'face',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PUNCH_METHOD_NOT_CONFIGURED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);

        // Méthode badge → pointage accepté.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'badge',
            ])
            ->assertCreated();

        // La méthode RÉELLEMENT utilisée est persistée (badge → card, valeur
        // historique du schéma).
        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'method' => 'card',
        ]);
    }

    public function test_face_method_requires_employee_face_enrollment(): void
    {
        [$manager] = $this->seedScenario();

        // Employé avec empreinte uniquement (pas de face).
        $employee = Employee::query()->where('email', 'karim@kiosk-method.test')->firstOrFail();

        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Face',
                'biometric_mode' => 'face',
                'punch_methods' => ['face'],
            ])
            ->assertCreated();
        $deviceCode = $kioskResponse->json('data.device_code');
        $this->assertIsString($deviceCode);
        $syncToken = $kioskResponse->json('data.sync_token');
        $this->assertIsString($syncToken);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'face',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'BIOMETRIC_NOT_ENABLED');
    }

    public function test_manager_method_requires_active_manager_of_same_tenant(): void
    {
        [$manager, $employee] = $this->seedScenario();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager, ['fingerprint', 'manager']);

        // Sans manager id → 422.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'manager',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'MANAGER_VALIDATION_REQUIRED');

        // Avec un simple employé (non manager) → 403.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'manager',
                'manager_employee_id' => $employee->id,
            ])
            ->assertStatus(403);

        // Avec le manager actif → pointage accepté, méthode `manager` persistée.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'method' => 'manager',
                'manager_employee_id' => $manager->id,
            ])
            ->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'method' => 'manager',
        ]);
    }

    public function test_kiosk_config_exposes_server_driven_methods(): void
    {
        [$manager] = $this->seedScenario();
        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Config',
                'punch_methods' => ['face', 'pin', 'manager'],
            ])
            ->assertCreated();
        $deviceCode = $kioskResponse->json('data.device_code');
        $this->assertIsString($deviceCode);
        $syncToken = $kioskResponse->json('data.sync_token');
        $this->assertIsString($syncToken);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/config')
            ->assertOk()
            ->assertJsonPath('data.punch_methods', ['face', 'pin', 'manager'])
            ->assertJsonPath('data.status', 'active');
    }

    /**
     * @return array{0: Employee, 1: Employee} [manager, employee]
     */
    protected function seedScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Methods',
            'slug' => 'company-methods-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-method.test',
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
            'last_name' => 'Methodes',
            'email' => 'karim@kiosk-method.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Methodes',
            'email' => 'manager@kiosk-method.test',
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

    /**
     * @param  list<string>  $methods
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(Employee $manager, array $methods = ['fingerprint']): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree',
                'biometric_mode' => 'fingerprint',
                'punch_methods' => $methods,
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }
}
