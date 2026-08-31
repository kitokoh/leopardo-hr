<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6546 (audit-secu M3) — GET /employees : extra_data brut exposait NID,
 * identifiant fiscal et groupe sanguin de TOUS les employés (RGPD sensibles).
 * Ces clés sont désormais masquées pour les viewers non autorisés (comme les
 * salaires, #5262) ; les clés non sensibles (department, job_title) restent
 * exposées pour les écrans équipe.
 */
class EmployeeExtraDataMaskingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        return Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
    }

    private function employee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        // extra_data complet avec clés sensibles + clés métier.
        DB::table('employees')
            ->where('id', $employee->id)
            ->update([
                'extra_data' => json_encode([
                    'department' => 'Ventes',
                    'job_title' => 'Commercial terrain',
                    'work_location' => 'Alger',
                    'education_level' => 'Master',
                    'national_id' => '123456789',
                    'tax_identifier' => 'TAX-98765',
                    'blood_group' => 'O+',
                ], JSON_THROW_ON_ERROR),
            ]);

        return $employee->refresh();
    }

    private function employeeToken(Employee $employee): string
    {
        return $employee->createToken('tests')->plainTextToken;
    }

    public function test_list_masks_sensitive_extra_data_keys_for_plain_employee(): void
    {
        $company = $this->company();
        $viewer = $this->employee($company, 'employee');
        $this->employee($company, 'employee');

        $this->withToken($this->employeeToken($viewer))
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('data.0.extra_data.department', 'Ventes')
            ->assertJsonPath('data.0.extra_data.job_title', 'Commercial terrain')
            ->assertJsonMissingPath('data.0.extra_data.national_id')
            ->assertJsonMissingPath('data.0.extra_data.tax_identifier')
            ->assertJsonMissingPath('data.0.extra_data.blood_group');
    }

    public function test_principal_manager_sees_full_extra_data(): void
    {
        $company = $this->company();
        $manager = $this->employee($company, 'manager', 'principal');
        $this->employee($company, 'employee');

        $this->withToken($this->employeeToken($manager))
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('data.1.extra_data.national_id', '123456789')
            ->assertJsonPath('data.1.extra_data.tax_identifier', 'TAX-98765')
            ->assertJsonPath('data.1.extra_data.blood_group', 'O+');
    }

    public function test_employee_self_detail_exposes_own_sensitive_data_but_not_others(): void
    {
        $company = $this->company();
        $viewer = $this->employee($company, 'employee');
        $other = $this->employee($company, 'employee');

        // Son propre dossier → clés sensibles visibles.
        $this->withToken($this->employeeToken($viewer))
            ->getJson("/api/v1/employees/{$viewer->id}")
            ->assertOk()
            ->assertJsonPath('data.extra_data.national_id', '123456789');

        // Le dossier d'un collègue → clés sensibles masquées.
        $this->withToken($this->employeeToken($viewer))
            ->getJson("/api/v1/employees/{$other->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.extra_data.national_id')
            ->assertJsonMissingPath('data.extra_data.tax_identifier')
            ->assertJsonMissingPath('data.extra_data.blood_group');
    }

    public function test_rh_manager_sees_full_extra_data(): void
    {
        $company = $this->company();
        $rh = $this->employee($company, 'manager', 'rh');
        $this->employee($company, 'employee');

        $this->withToken($this->employeeToken($rh))
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('data.1.extra_data.national_id', '123456789');
    }
}
