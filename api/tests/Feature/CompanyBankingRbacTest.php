<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyBankingController;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6544 (audit-secu M1) — GET /company/banking expose l'IBAN/BIC de
 * l'entreprise à tout employé authentifié. La lecture doit être réservée
 * aux managers principal/rh/comptable, comme l'écriture.
 */
class CompanyBankingRbacTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        // Coordonnées bancaires dans metadata.
        DB::table(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->where('id', $company->id)
            ->update([
                'metadata' => json_encode([
                    'company_iban' => 'FR7630006000011234567890189',
                    'company_bic' => 'BDFEFRPP',
                ], JSON_THROW_ON_ERROR),
            ]);

        return $company;
    }

    private function employee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        return $employee;
    }

    public function test_employee_cannot_read_company_banking(): void
    {
        $company = $this->company();
        $employee = $this->employee($company, 'employee');

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/company/banking')->assertStatus(403);
    }

    public function test_principal_manager_can_read_company_banking(): void
    {
        $company = $this->company();
        $manager = $this->employee($company, 'manager', 'principal');

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/company/banking')
            ->assertOk()
            ->assertJsonPath('data.company_iban', 'FR7630006000011234567890189')
            ->assertJsonPath('data.sepa_ready', true);
    }

    public function test_comptable_role_can_read_company_banking(): void
    {
        $company = $this->company();
        $accountant = $this->employee($company, 'manager', 'comptable');

        Sanctum::actingAs($accountant);

        $this->getJson('/api/v1/company/banking')->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/company/banking')->assertStatus(401);
    }
}
