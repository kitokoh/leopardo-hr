<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7646 (modèles orphelins) — les modèles Cabinet vivaient dans le
 * schéma partagé shared_tenants SANS le trait BelongsToCompany : aucune
 * isolation en lecture (ShareDocument::handle faisait un findOrFail non
 * filtré) et `company_id` mass-assignable en écriture. Ces tests verrouillent
 * le nouveau contrat :
 *   - un document d'un autre tenant est invisible (404 par route-model
 *     binding scopé, plus de fuite par identifiant deviné) ;
 *   - `company_id` fourni dans un payload est ignoré (plus fillable) et le
 *     trait force le tenant courant à la création ;
 *   - `company_id` ne peut pas être déplacé vers un autre tenant sur update
 *     (nouvelle garde `updating` du trait).
 */
class CabinetTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $this->employeeA = $employeeA;
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        $this->employeeB = $employeeB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    public function test_document_of_another_tenant_is_not_readable(): void
    {
        /** @var CabinetDocument $documentA */
        $documentA = CabinetDocument::forceCreate([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA->id,
            'name' => 'Contrat confidentiel',
            'original_name' => 'contrat.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'cabinet/a/contrat.pdf',
        ]);

        Sanctum::actingAs($this->employeeB);

        $this->getJson("/api/v1/cabinet/documents/{$documentA->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/cabinet/documents/{$documentA->id}/download")
            ->assertNotFound();
    }

    public function test_folder_creation_ignores_spoofed_company_id(): void
    {
        Sanctum::actingAs($this->employeeA);

        $response = $this->postJson('/api/v1/cabinet/folders', [
            'name' => 'Dossier spoof',
            'company_id' => $this->companyB->id, // spoof : tenant B visé
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('cabinet_folders', [
            'name' => 'Dossier spoof',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('cabinet_folders', [
            'name' => 'Dossier spoof',
            'company_id' => $this->companyB->id,
        ]);
    }

    public function test_update_cannot_move_record_to_another_tenant(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var CabinetFolder $folder */
        $folder = CabinetFolder::query()->create([
            'employee_id' => $this->employeeA->id,
            'name' => 'Dossier A',
        ]);

        $this->assertSame($this->companyA->id, $folder->company_id);

        // Tentative de déplacement cross-tenant sur le chemin update :
        // la garde `updating` du trait restaure la valeur d'origine.
        $folder->company_id = $this->companyB->id;
        $folder->save();
        $folder->refresh();

        $this->assertSame($this->companyA->id, $folder->company_id);
    }

    public function test_out_of_tenant_context_behaviour_is_preserved(): void
    {
        // Jobs/CLI/seeders : hors tenant, forceCreate conserve la valeur
        // fournie et update reste permissif (chemin documenté du trait).
        /** @var CabinetFolder $folder */
        $folder = CabinetFolder::forceCreate([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'name' => 'Dossier maintenance',
        ]);

        $this->assertSame($this->companyB->id, $folder->company_id);

        $folder->company_id = $this->companyA->id;
        $folder->save();
        $folder->refresh();

        $this->assertSame($this->companyA->id, $folder->company_id);
    }
}
