<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\Cabinet\Domain\Models\CabinetShare;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4798 — la route publique GET /api/v1/cabinet/shared/{token} doit résoudre
 * le partage dans le bon schéma tenant (plus de lecture cross-tenant sur
 * worker persistant) sans authentification.
 */
class CabinetShareAccessByTokenTest extends TestCase
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

    public function test_public_token_access_downloads_document_without_auth(): void
    {
        $document = CabinetDocument::create([
            'company_id'    => $this->company->id,
            'employee_id'   => $this->employee->id,
            'name'          => 'contrat.pdf',
            'original_name' => 'Contrat.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 1024,
            'disk'          => 'local',
            'path'          => 'cabinet/contrat.pdf',
        ]);

        Storage::disk('local')->put('cabinet/contrat.pdf', 'PDF-BYTES');

        $share = CabinetShare::create([
            'company_id'    => $this->company->id,
            'employee_id'   => $this->employee->id,
            'shareable_type' => CabinetDocument::class,
            'shareable_id'  => $document->id,
            'share_token'   => 'token-document-4798',
            'shared_via'    => 'link',
            'expires_at'    => Carbon::now()->addDay(),
        ]);

        $response = $this->get('/api/v1/cabinet/shared/token-document-4798');

        $response->assertOk();
        $this->assertSame('PDF-BYTES', $response->streamedContent());
        $this->assertSame('token-document-4798', $share->share_token);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/api/v1/cabinet/shared/unknown-token-4798')->assertNotFound();
    }

    public function test_expired_share_returns_410(): void
    {
        $document = CabinetDocument::create([
            'company_id'    => $this->company->id,
            'employee_id'   => $this->employee->id,
            'name'          => 'a.pdf',
            'original_name' => 'a.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 10,
            'disk'          => 'local',
            'path'          => 'cabinet/a.pdf',
        ]);

        CabinetShare::create([
            'company_id'     => $this->company->id,
            'employee_id'    => $this->employee->id,
            'shareable_type' => CabinetDocument::class,
            'shareable_id'   => $document->id,
            'share_token'    => 'token-expired-4798',
            'shared_via'     => 'link',
            'expires_at'     => Carbon::now()->subDay(),
        ]);

        $this->get('/api/v1/cabinet/shared/token-expired-4798')
            ->assertStatus(410)
            ->assertJson(['error' => 'SHARE_EXPIRED']);
    }

    public function test_folder_share_returns_documents_json(): void
    {
        $folder = CabinetFolder::create([
            'company_id'  => $this->company->id,
            'employee_id' => $this->employee->id,
            'name'        => 'Dossier partagé',
        ]);

        CabinetDocument::create([
            'company_id'    => $this->company->id,
            'employee_id'   => $this->employee->id,
            'name'          => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 10,
            'disk'          => 'local',
            'path'          => 'cabinet/doc.pdf',
            'folder_id'     => $folder->id,
        ]);

        CabinetShare::create([
            'company_id'     => $this->company->id,
            'employee_id'    => $this->employee->id,
            'shareable_type' => CabinetFolder::class,
            'shareable_id'   => $folder->id,
            'share_token'    => 'token-folder-4798',
            'shared_via'     => 'link',
            'expires_at'     => Carbon::now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/cabinet/shared/token-folder-4798');

        $response->assertOk()
            ->assertJsonPath('data.type', 'folder')
            ->assertJsonCount(1, 'data.documents');
    }
}
