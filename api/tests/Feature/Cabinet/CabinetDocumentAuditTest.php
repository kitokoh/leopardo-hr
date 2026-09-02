<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC20 (Documents & Evidence, #5896) — piste d'audit du Cabinet.
 *
 * L'upload, la suppression et le déplacement d'un document de Cabinet sont
 * journalisés dans `audit_logs` (module=cabinet) — exigence backlog :
 * « une suppression respecte l'audit nécessaire ».
 */
class CabinetDocumentAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    public function test_upload_is_audited_with_document_metadata(): void
    {
        Sanctum::actingAs($this->manager);

        $file = UploadedFile::fake()->create('contrat.pdf', 512, 'application/pdf');

        $response = $this->postJson('/api/v1/cabinet/documents', [
            'file' => $file,
            'name' => 'Contrat 2024',
        ]);

        $response->assertStatus(201);

        $document = CabinetDocument::query()->firstOrFail();
        $audit = AuditLog::query()
            ->where('module', 'cabinet')
            ->where('action', 'cabinet.document.uploaded')
            ->firstOrFail();

        self::assertSame((string) $this->company->id, (string) $audit->company_id);
        self::assertSame((string) $this->manager->id, (string) $audit->user_id);
        self::assertSame($document->getMorphClass(), $audit->auditable_type);
        self::assertSame((string) $document->id, (string) $audit->auditable_id);
        self::assertSame('Contrat 2024', $audit->metadata['name'] ?? null);
        self::assertSame('application/pdf', $audit->metadata['mime_type'] ?? null);
    }

    public function test_delete_is_audited_before_deletion_with_old_values(): void
    {
        Sanctum::actingAs($this->manager);

        $document = $this->createDocument();

        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")
            ->assertStatus(204);

        self::assertDatabaseMissing('cabinet_documents', ['id' => $document->id]);

        $audit = AuditLog::query()
            ->where('module', 'cabinet')
            ->where('action', 'cabinet.document.deleted')
            ->firstOrFail();

        // L'audit conserve les références AVANT suppression.
        self::assertSame((string) $document->id, (string) $audit->auditable_id);
        self::assertSame('Contrat audit.pdf', $audit->old_values['original_name'] ?? null);
        self::assertSame($document->path, $audit->old_values['path'] ?? null);
    }

    public function test_move_is_audited_with_old_and_new_folder(): void
    {
        Sanctum::actingAs($this->manager);

        $folder = CabinetFolder::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->manager->id,
            'name' => 'RH',
        ]);
        $document = $this->createDocument();

        $this->patchJson("/api/v1/cabinet/documents/{$document->id}/move", [
            'folder_id' => $folder->id,
        ])->assertOk();

        $audit = AuditLog::query()
            ->where('module', 'cabinet')
            ->where('action', 'cabinet.document.moved')
            ->firstOrFail();

        self::assertNull($audit->old_values['folder_id'] ?? null);
        $newFolderId = $audit->new_values['folder_id'] ?? null;
        // folder_id stocké en int natif (JSON) — comparaison typée via (string).
        self::assertSame((string) $folder->id, (string) $newFolderId);
    }

    public function test_read_only_document_deletion_is_refused_and_not_audited(): void
    {
        Sanctum::actingAs($this->manager);

        $document = $this->createDocument(['read_only' => true]);

        $this->deleteJson("/api/v1/cabinet/documents/{$document->id}")
            ->assertStatus(403);

        self::assertDatabaseHas('cabinet_documents', ['id' => $document->id]);
        self::assertSame(
            0,
            AuditLog::query()
                ->where('module', 'cabinet')
                ->where('action', 'cabinet.document.deleted')
                ->count(),
            'aucune entrée d\'audit de suppression pour un refus (read_only)'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(array $attributes = []): CabinetDocument
    {
        /** @var CabinetDocument $document */
        $document = CabinetDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->manager->id,
            'name' => 'Contrat audit',
            'original_name' => 'Contrat audit.pdf',
            'path' => 'cabinet/'.$this->company->id.'/contrat-audit.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 512,
            'read_only' => false,
            ...$attributes,
        ]);

        return $document;
    }
}
