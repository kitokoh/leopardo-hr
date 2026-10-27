<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7962 — extraction des cas d'usage lecture du KioskController.
 *
 * Le contrôleur (980 lignes) portait la requête et la sérialisation du roster
 * et de la fiche employé. Elles vivent désormais dans
 * `BuildKioskRoster` / `BuildKioskEmployeeInfo`. Ces tests verrouillent le
 * CONTRAT HTTP : l'extraction ne doit rien changer pour le kiosque.
 */
class KioskRosterExtractionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_roster_returns_only_active_biometric_employees(): void
    {
        [$manager, $deviceCode, $token] = $this->seedKiosk();

        $response = $this->withHeader('X-Kiosk-Token', $token)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/roster')
            ->assertOk();

        $employees = $response->json('data.employees');
        $matricules = array_column($employees, 'matricule');

        $this->assertContains('EMP-001', $matricules);
        $this->assertNotContains('NB-001', $matricules, 'un employé sans biométrie ne doit pas apparaître');
        $this->assertNotContains('INACTIF-001', $matricules, 'un employé inactif ne doit pas apparaître');

        $karim = collect($employees)->firstWhere('matricule', 'EMP-001');
        $this->assertSame('Karim Employe', $karim['name']);
        $this->assertTrue($karim['fingerprint_enabled']);
        $this->assertFalse($karim['face_enabled']);
    }

    public function test_employee_info_is_tenant_scoped(): void
    {
        [$manager, $deviceCode, $token] = $this->seedKiosk();

        $this->withHeader('X-Kiosk-Token', $token)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/employee-info', ['identifier' => 'EMP-001'])
            ->assertOk()
            ->assertJsonPath('data.employee.matricule', 'EMP-001');

        // Un identifiant d'une autre société ne doit jamais être résolu.
        $this->withHeader('X-Kiosk-Token', $token)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/employee-info', ['identifier' => 'FOREIGN-001'])
            ->assertNotFound();
    }

    public function test_leave_balance_uses_the_same_tenant_scoped_resolution(): void
    {
        [$manager, $deviceCode, $token] = $this->seedKiosk();

        $this->withHeader('X-Kiosk-Token', $token)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/leave-balance', ['identifier' => 'EMP-001'])
            ->assertOk()
            ->assertJsonPath('data.employee_name', 'Karim Employe');

        $this->withHeader('X-Kiosk-Token', $token)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/leave-balance', ['identifier' => 'FOREIGN-001'])
            ->assertNotFound();
    }

    /**
     * @return array{0: Employee, 1: string, 2: string}
     */
    private function seedKiosk(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Roster',
            'slug' => 'company-roster-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-roster.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        $foreign = Company::query()->create([
            'name' => 'Company Foreign',
            'slug' => 'company-foreign-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-foreign.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->makeEmployee($company, [
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@kiosk-roster.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_face_enabled' => false,
        ]);

        $this->makeEmployee($company, [
            'first_name' => 'Sans',
            'last_name' => 'Biometrie',
            'email' => 'nobio@kiosk-roster.test',
            'matricule' => 'NB-001',
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
        ]);

        $this->makeEmployee($company, [
            'first_name' => 'Parti',
            'last_name' => 'Ailleurs',
            'email' => 'inactif@kiosk-roster.test',
            'matricule' => 'INACTIF-001',
            'biometric_fingerprint_enabled' => true,
        ], 'departed');

        $this->makeEmployee($foreign, [
            'first_name' => 'Autre',
            'last_name' => 'Societe',
            'email' => 'foreign@kiosk-foreign.test',
            'matricule' => 'FOREIGN-001',
            'biometric_fingerprint_enabled' => true,
        ]);

        $manager = $this->makeEmployee($company, [
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@kiosk-roster.test',
        ], 'active', 'manager');

        DB::statement('SET search_path TO public');

        $kiosk = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        return [$manager, $kiosk->json('data.device_code'), $kiosk->json('data.sync_token')];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEmployee(
        Company $company,
        array $attributes,
        string $status = 'active',
        string $role = 'employee',
    ): Employee {
        $employee = new Employee($attributes);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $role === 'manager' ? 'principal' : null,
            'status' => $status,
        ])->save();

        return $employee;
    }
}
