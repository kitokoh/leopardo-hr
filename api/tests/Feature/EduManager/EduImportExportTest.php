<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduImport;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduExportService;
use App\Modules\EduManager\Infrastructure\Services\EduImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5833 (EDU-017) — import/export sécurisé.
 *
 * Verrouille : preview SANS écriture cible, en-têtes validés, commit
 * idempotent (statuts terminaux), rapport d'erreurs, audit, export CSV
 * auditée, isolation tenant.
 */
class EduImportExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;
    }

    private function csvFile(string $content, string $name = 'students.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_import_tables_exist_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_imports'));
        $this->assertTrue(Schema::hasTable('edu_exports'));
    }

    public function test_preview_validates_headers_without_writing(): void
    {
        $service = app(EduImportService::class);
        $file = $this->csvFile("mauvais,en-tetes\n1,2\n");

        try {
            $service->preview($this->principalA, $file, EduImport::ENTITY_STUDENTS);
            $this->fail('En-têtes invalides auraient dû être refusés.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('EDU_IMPORT_HEADERS', $exception->getMessage());
        }

        $this->assertSame(0, EduStudent::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(0, EduImport::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_preview_then_commit_imports_students_idempotently(): void
    {
        $service = app(EduImportService::class);
        $file = $this->csvFile(
            "student_number,display_name,birth_date,status\nSTU-1,Lina Benali,2014-03-12,active\nSTU-2,Yacine Meziane,2013-07-01,active\n"
        );

        $import = $service->preview($this->principalA, $file, EduImport::ENTITY_STUDENTS);

        $this->assertSame(EduImport::STATUS_PREVIEWED, $import->status);
        $this->assertSame(2, (int) $import->valid_rows);
        // Preview : aucune écriture cible.
        $this->assertSame(0, EduStudent::query()->where('company_id', $this->companyA->id)->count());

        $committed = $service->commit($this->principalA, $import);
        $this->assertSame(EduImport::STATUS_COMMITTED, $committed->status);
        $this->assertSame(2, EduStudent::query()->where('company_id', $this->companyA->id)->count());

        // Commit d'une session déjà committée → refus (idempotence stricte).
        try {
            $service->commit($this->principalA, $committed);
            $this->fail('Un import terminal aurait dû refuser le re-commit.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('EDU_IMPORT_TERMINAL', $exception->getMessage());
        }

        $this->assertSame(2, EduStudent::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_commit_reports_row_errors_and_audits(): void
    {
        $service = app(EduImportService::class);
        $file = $this->csvFile(
            "student_number,display_name,birth_date,status\nSTU-1,Lina Benali,2014-03-12,active\nSTU-2,Yacine Meziane,2014-03-12,inconnu\n"
        );

        $import = $service->preview($this->principalA, $file, EduImport::ENTITY_STUDENTS);
        $committed = $service->commit($this->principalA, $import);

        // 1 ligne OK, 1 ligne invalide (statut hors bornes) → rapportée en erreur.
        $this->assertSame(1, (int) $committed->valid_rows);
        $this->assertSame(1, (int) $committed->error_rows);
        $this->assertNotEmpty($committed->errors);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'edu.import.previewed',
            'module' => 'edu',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'edu.import.committed',
            'module' => 'edu',
        ]);
    }

    public function test_preview_masks_sensitive_data(): void
    {
        $service = app(EduImportService::class);
        $file = $this->csvFile("student_number,display_name,birth_date,status\nSTU-1,Lina Benali,2014-03-12,active\n");

        $import = $service->preview($this->principalA, $file, EduImport::ENTITY_STUDENTS);

        $sample = $import->preview_data ?? [];
        $this->assertNotEmpty($sample);
        $this->assertStringNotContainsString('Lina', (string) json_encode($sample));
        $this->assertStringNotContainsString('2014', (string) json_encode($sample));
    }

    public function test_other_tenant_import_is_rejected(): void
    {
        $service = app(EduImportService::class);
        $file = $this->csvFile("student_number,display_name,birth_date,status\nSTU-1,Lina Benali,2014-03-12,active\n");
        $import = $service->preview($this->principalA, $file, EduImport::ENTITY_STUDENTS);

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->expectException(NotFoundHttpException::class);

        $service->commit($principalB, $import);
    }

    public function test_export_is_audited_and_tenant_scoped(): void
    {
        // Un élève du tenant A.
        EduStudent::query()->create([
            'company_id' => $this->companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        // Un élève du tenant B (ne doit JAMAIS apparaître).
        EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $result = app(EduExportService::class)
            ->export($this->principalA, 'students');

        $this->assertStringContainsString('STU-A-1', $result['content']);
        $this->assertStringNotContainsString('STU-B-1', $result['content']);
        $this->assertSame(1, $result['count']);

        $this->assertDatabaseHas('edu_exports', [
            'company_id' => $this->companyA->id,
            'kind' => 'students',
            'record_count' => 1,
            'exported_by' => $this->principalA->id,
        ]);
    }
}
