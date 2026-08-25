<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Revue #5445 — isolation tenant fail-closed des endpoints sensibles
 * (export/téléchargement/paie/documents).
 *
 * Pour chaque endpoint : la ressource d'un AUTRE tenant doit répondre 404
 * (pas 403, pas de fuite d'existence — leçon fail-closed #3727). RBAC :
 * rôle insuffisant → 403.
 */
class SensitiveEndpointsTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $managerA;

    private Employee $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ']);
        $this->companyA = $companyA;
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ']);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->managerA = $managerA;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->managerB = $managerB;
    }

    public function test_absence_proof_cross_tenant_returns_404(): void
    {
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $absenceType = AbsenceType::factory()->create(['company_id' => $this->companyA->id]);
        $absence = Absence::factory()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $employeeA->id,
            'absence_type_id' => $absenceType->id,
            'status' => 'approved',
            'proof_path' => 'proofs/absence-a.pdf',
        ]);

        Storage::disk('local')->put('proofs/absence-a.pdf', 'x');

        Sanctum::actingAs($this->managerB, ['*']);

        $this->getJson("/api/v1/absences/{$absence->id}/proof")
            ->assertNotFound();

        Storage::disk('local')->delete('proofs/absence-a.pdf');
    }

    public function test_salary_advance_proof_cross_tenant_returns_404(): void
    {
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $advance = SalaryAdvance::factory()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $employeeA->id,
            'proof_path' => 'proofs/advance-a.pdf',
        ]);

        Storage::disk('local')->put('proofs/advance-a.pdf', 'x');

        Sanctum::actingAs($this->managerB, ['*']);

        $this->getJson("/api/v1/salary-advances/{$advance->id}/proof")
            ->assertNotFound();

        Storage::disk('local')->delete('proofs/advance-a.pdf');
    }

    public function test_cabinet_document_download_cross_tenant_returns_404(): void
    {
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $document = CabinetDocument::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $employeeA->id,
            'name' => 'contrat-a.pdf',
            'original_name' => 'contrat-a.pdf',
            'mime_type' => 'application/pdf',
            'size' => 42,
            'disk' => 'local',
            'path' => 'cabinet/contrat-a.pdf',
        ]);

        Storage::disk('local')->put('cabinet/contrat-a.pdf', 'x');

        Sanctum::actingAs($this->managerB, ['*']);

        $this->getJson("/api/v1/cabinet/documents/{$document->id}/download")
            ->assertNotFound();

        Storage::disk('local')->delete('cabinet/contrat-a.pdf');
    }

    public function test_export_employees_same_tenant_manager_ok_and_employee_forbidden(): void
    {
        Sanctum::actingAs($this->managerA, ['*']);

        $this->getJson('/api/v1/export/employees')
            ->assertOk();

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employeeA, ['*']);

        $this->getJson('/api/v1/export/employees')
            ->assertForbidden();
    }
}
