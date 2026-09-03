<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\Cabinet\Domain\Models\CabinetShare;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6674 — un partage « orphelin » (shareable supprimé) ou au shareable_type
 * legacy (classe supprimée, refactor DDD v4.21.0) faisait 500 sur
 * GET /api/v1/cabinet/shares (eager-load morph `with('shareable')`).
 *
 * Corrections couvertes :
 * 1. index() et accessByToken() ne traitent que les types supportés ;
 * 2. la suppression d'un document/dossier révoque ses partages (plus
 *    d'orphelins créés) ;
 * 3. un shareable supprimé (orphelin historique) reste listé sans 500
 *    (shareable_name null).
 */
class CabinetShareOrphanRegressionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'active']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->employee = $employee;
    }

    private function makeShare(string $shareableType, int $shareableId, string $token): CabinetShare
    {
        return CabinetShare::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'shareable_type' => $shareableType,
            'shareable_id' => $shareableId,
            'share_token' => $token,
            'shared_via' => 'link',
            'expires_at' => Carbon::now()->addDay(),
        ]);
    }

    public function test_list_ignores_legacy_shareable_type_without_500(): void
    {
        $folder = CabinetFolder::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'name' => 'Dossier #6674',
        ]);

        $valid = $this->makeShare(CabinetFolder::class, $folder->id, 'token-valid-6674');
        // shareable_type legacy : classe supprimée par la migration DDD —
        // faisait 500 sur l'eager-load morph avant le correctif (#6674).
        $this->makeShare('App\\Models\\LegacyCabinetFolder', 1, 'token-legacy-6674');

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/cabinet/shares');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $valid->id)
            ->assertJsonPath('data.0.shareable_name', $folder->name);
    }

    public function test_list_tolerates_orphan_share_with_deleted_shareable(): void
    {
        // Share valide dont le shareable n'existe plus (orphelin historique) :
        // doit répondre 200 avec shareable_name null — pas de 500.
        $this->makeShare(CabinetFolder::class, 999999, 'token-orphan-6674');

        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/cabinet/shares')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shareable_name', null);
    }

    public function test_deleting_document_revokes_its_shares(): void
    {
        $document = CabinetDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'name' => 'doc-6674.pdf',
            'original_name' => 'doc-6674.pdf',
            'mime_type' => 'application/pdf',
            'size' => 128,
            'disk' => 'local',
            'path' => 'cabinet/doc-6674.pdf',
        ]);
        Storage::disk('local')->put('cabinet/doc-6674.pdf', 'PDF');

        $this->makeShare(CabinetDocument::class, $document->id, 'token-doc-6674');

        Sanctum::actingAs($this->employee);

        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")->assertStatus(204);

        // Le partage du document supprimé ne doit plus apparaître (révoqué).
        $this->getJson('/api/v1/cabinet/shares')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_deleting_folder_revokes_folder_and_document_shares(): void
    {
        $folder = CabinetFolder::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'name' => 'Dossier racine #6674',
        ]);

        $document = CabinetDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'folder_id' => $folder->id,
            'name' => 'sous-doc.pdf',
            'original_name' => 'sous-doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 128,
            'disk' => 'local',
            'path' => 'cabinet/sous-doc.pdf',
        ]);
        Storage::disk('local')->put('cabinet/sous-doc.pdf', 'PDF');

        $this->makeShare(CabinetFolder::class, $folder->id, 'token-folder-6674');
        $this->makeShare(CabinetDocument::class, $document->id, 'token-doc-in-folder-6674');

        Sanctum::actingAs($this->employee);

        $this->deleteJson("/api/v1/cabinet/folders/{$folder->id}")->assertStatus(204);

        $this->getJson('/api/v1/cabinet/shares')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
