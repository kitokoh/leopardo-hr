<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #6684 — régression : `SectorTemplateService::resolveDefaultDepartmentId()`
 * et le seed des positions inséraient `updated_at`, colonne ABSENTE des tables
 * réelles `departments`/`positions` (migration tenant 000100 : created_at seul).
 * Résultat en prod : POST /platform/companies → 500 SQLSTATE 42703.
 *
 * Le schéma MVP (CreatesMvpSchema) portait `$table->timestamps()` et masquait
 * le bug en CI. Le schéma de test est désormais aligné sur la migration réelle
 * (created_at seul) — ce test exerce donc le vrai chemin et doit passer.
 */
class SectorTemplateDepartmentsPositionsSchemaTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function apply_template_creates_departments_and_positions_without_updated_at(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['sector' => 'btp']);

        $service = new SectorTemplateService;

        // Avant le correctif : 500 SQLSTATE 42703 (column "updated_at" does not exist)
        // sur le schéma réel (PostgreSQL) — sur le schéma MVP timestamps() le bug passait.
        $service->applyTemplate($company);

        $department = DB::table('departments')->where('company_id', $company->id)->first();
        $this->assertNotNull($department, 'Un département par défaut doit être créé.');
        $this->assertNotNull($department->created_at);

        $positions = DB::table('positions')->where('company_id', $company->id)->get();
        $this->assertNotEmpty($positions, 'Les positions du secteur doivent être seedées.');
    }
}
