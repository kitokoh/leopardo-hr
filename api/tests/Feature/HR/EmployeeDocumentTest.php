<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\EmployeeDocument;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Checklist documents du dossier employé (issue #5326 — gap G3).
 *
 * Couvre : CRUD scopé tenant, RBAC (écriture principal/rh, self-service
 * employé en lecture seule), badge « dossier complet » sur la fiche employé
 * et isolation cross-tenant (404).
 */
class EmployeeDocumentTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_can_create_and_read_document(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/employee-documents', [
            'employee_id' => $employee->id,
            'type' => 'contract_signed',
            'status' => 'uploaded',
            'document_date' => '2026-08-20',
            'reference' => 'CTR-2026-001',
            'url' => 'files/contracts/ctr-2026-001.pdf',
            'notes' => 'Contrat CDI signé.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.type', 'contract_signed')
            ->assertJsonPath('data.status', 'uploaded')
            ->assertJsonPath('data.company_id', $company->id);

        $this->assertDatabaseHas('employee_documents', [
            'id' => $response->json('data.id'),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'uploaded_by' => $manager->id,
        ]);

        // Lecture détaillée.
        $this->getJson('/api/v1/employee-documents/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.reference', 'CTR-2026-001');
    }

    public function test_employee_cannot_create_or_update_document(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/employee-documents', [
            'employee_id' => $employee->id,
            'type' => 'employee_file',
        ])->assertForbidden();

        $document = $this->makeDocument($company, $employee);

        $this->putJson('/api/v1/employee-documents/'.$document->id, [
            'status' => 'generated',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/employee-documents/'.$document->id)->assertForbidden();
    }

    public function test_manager_can_filter_documents_by_employee_and_type(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        [$secondCompany, $secondManager, $secondEmployee] = $this->createActors('MA');

        $contractDoc = $this->makeDocument($company, $employee, 'contract_signed');
        $fileDoc = $this->makeDocument($company, $employee, 'employee_file');
        $otherDoc = $this->makeDocument($secondCompany, $secondEmployee, 'contract_signed');

        Sanctum::actingAs($manager);

        // Filtre employé + type.
        $this->getJson('/api/v1/employee-documents?employee_id='.$employee->id.'&type=contract_signed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contractDoc->id);

        // Sans filtre : tous les documents du tenant A uniquement.
        $all = $this->getJson('/api/v1/employee-documents');
        $all->assertOk()->assertJsonCount(2, 'data');
        /** @var list<array{id: int}> $documents */
        $documents = $all->json('data');
        $ids = collect($documents)->pluck('id')->all();
        $this->assertNotContains($otherDoc->id, $ids);
    }

    public function test_employee_self_service_reads_only_own_documents(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        [$secondCompany, $secondManager, $secondEmployee] = $this->createActors('MA');

        $own = $this->makeDocument($company, $employee, 'employee_file');
        $this->makeDocument($company, $manager, 'contract_signed');
        $this->makeDocument($secondCompany, $secondEmployee, 'contract_signed');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/documents');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.type_label', 'Fiche employé');
    }

    public function test_cross_tenant_document_is_404(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        [, $foreignManager] = $this->createActors('MA');

        $document = $this->makeDocument($company, $employee, 'contract_signed');

        Sanctum::actingAs($foreignManager);

        $this->getJson('/api/v1/employee-documents/'.$document->id)->assertNotFound();
        $this->putJson('/api/v1/employee-documents/'.$document->id, ['status' => 'generated'])->assertNotFound();
        $this->deleteJson('/api/v1/employee-documents/'.$document->id)->assertNotFound();
    }

    public function test_dossier_badge_complete_and_incomplete_on_employee_fiche(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($manager);

        // Dossier incomplet : il manque la fiche employé.
        $this->makeDocument($company, $employee, 'contract_signed');

        $this->getJson('/api/v1/employees/'.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.documents_status.complete', false)
            ->assertJsonPath('data.documents_status.present', ['contract_signed'])
            ->assertJsonPath('data.documents_status.missing', ['employee_file']);

        // Dossier complet (statut active : contrat signé + fiche employé).
        $this->makeDocument($company, $employee, 'employee_file');

        $this->getJson('/api/v1/employees/'.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.documents_status.complete', true)
            ->assertJsonCount(0, 'data.documents_status.missing');
    }

    public function test_dossier_badge_ignores_missing_status_rows(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        // Une ligne « missing » ne satisfait pas le type requis : le type reste
        // compté manquant, comme ceux sans ligne du tout (docblock du service).
        $this->makeDocument($company, $employee, 'contract_signed', 'missing');

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/employees/'.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.documents_status.complete', false)
            ->assertJsonPath('data.documents_status.missing', ['contract_signed', 'employee_file']);
    }

    public function test_update_document_keeps_tenant_and_changes_fields(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $document = $this->makeDocument($company, $employee, 'employee_file');

        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/employee-documents/'.$document->id, [
            'status' => 'generated',
            'reference' => 'FICHE-001',
        ])->assertOk()
            ->assertJsonPath('data.status', 'generated')
            ->assertJsonPath('data.reference', 'FICHE-001');

        $this->assertDatabaseHas('employee_documents', [
            'id' => $document->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_store_rejects_foreign_employee_and_invalid_type(): void
    {
        [$company, $manager] = $this->createActors();
        [$foreignCompany, $foreignManager, $foreignEmployee] = $this->createActors('MA');

        Sanctum::actingAs($manager);

        // Employé d'un autre tenant → 422 (règle d'existence scopée).
        $this->postJson('/api/v1/employee-documents', [
            'employee_id' => $foreignEmployee->id,
            'type' => 'employee_file',
        ])->assertStatus(422);

        // Type hors liste → 422.
        $this->postJson('/api/v1/employee-documents', [
            'employee_id' => $manager->id,
            'type' => 'pay_slip',
        ])->assertStatus(422);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createActors(string $country = 'DZ'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => $country,
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, 'manager.'.$country.'.docs@a.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.'.$country.'.docs@a.test', 'employee', null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }

    private function makeDocument(Company $company, Employee $employee, string $type = 'employee_file', string $status = 'received'): EmployeeDocument
    {
        /** @var EmployeeDocument $document */
        $document = EmployeeDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => $type,
            'status' => $status,
        ]);

        return $document;
    }
}
