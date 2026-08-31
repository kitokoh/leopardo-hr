<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Import/export sécurisé FuelStation — FUEL-018 (issue #5812).
 *
 * Couvre : preview CSV (aucun effet sur les tables cibles), validation
 * ligne par ligne, commit idempotent avec rollback logique, annulation,
 * historique, RBAC deny-by-default, isolation tenant 404, export CSV depuis
 * les snapshots de reporting.
 * API imports CSV sécurisés FuelStation — FUEL-018 (issue #5812).
 *
 * Couvre : RBAC (employé 403), preview dry-run sans écriture, import
 * produits appliqué, rollback logique (ligne invalide → zéro écriture),
 * limites (fichier trop gros), journal d'import audité, cross-tenant 404.
 */
class FuelImportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/imports')->assertStatus(401);
    }

    public function test_manager_previews_and_commits_product_import(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $csv = "code,name,unit_code\nessence,Essence sans plomb,l\ndiesel,Gazole,l\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'products',
            'file' => $file,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.valid_rows', 2)
            ->assertJsonPath('data.error_rows', 0)
            ->assertJsonPath('data.status', 'previewed');

        // Le preview ne touche JAMAIS les tables cibles.
        $this->assertSame(0, FuelProduct::query()->count());

        $import = FuelImport::query()->firstOrFail();

        $this->postJson("/api/v1/fuel-station/imports/{$import->id}/commit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'committed')
            ->assertJsonPath('data.result.created', 2);

        $this->assertSame(2, FuelProduct::query()->count());

        // Rejeu idempotent : déjà committed → état existant, pas de doublon.
        $this->postJson("/api/v1/fuel-station/imports/{$import->id}/commit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'committed');

        $this->assertSame(2, FuelProduct::query()->count());
    }

    public function test_preview_reports_line_errors_without_commit(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $csv = "code,name,unit_code\nessence,Essence sans plomb,l\n,ligne invalide,l\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'products',
            'file' => $file,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.error_rows', 1)
            ->assertJsonPath('meta.errors.0.line', 3);

        $this->assertSame(0, FuelProduct::query()->count());
    }

    public function test_readings_import_is_rejected_at_preview(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        // Les relevés passent par l'API idempotente (FUEL-004) — jamais par
        // un import CSV : le preview doit rejeter toutes les lignes.
        $csv = "meter_id,reading_value_minor\n99999,100\n";
        $file = UploadedFile::fake()->createWithContent('readings.csv', $csv);

        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'readings',
            'file' => $file,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.valid_rows', 0)
            ->assertJsonPath('data.error_rows', 1)
            ->assertJsonPath('meta.errors.0.line', 2);
    }

    public function test_pump_import_requires_station_id(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $csv = "code,product_types\nP-01,essence\n";
        $file = UploadedFile::fake()->createWithContent('pumps.csv', $csv);

        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'pumps',
            'file' => $file,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.valid_rows', 0)
            ->assertJsonPath('data.error_rows', 1)
            ->assertJsonPath('meta.errors.0.error', 'station_id requis (numérique)');
    }

    public function test_manager_cancels_import(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $csv = "code,name,unit_code\nessence,Essence,l\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'products',
            'file' => $file,
        ])->assertStatus(200);

        $import = FuelImport::query()->firstOrFail();

        $this->postJson("/api/v1/fuel-station/imports/{$import->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, FuelProduct::query()->count());
    }

    public function test_operator_gets_403_on_imports(): void
    {
        [$company, , $operator] = $this->seedTenantWithOperator();

        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/imports')->assertStatus(403);

        $csv = "code,name,unit_code\nessence,Essence,l\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);
        $this->post('/api/v1/fuel-station/imports/preview', [
            'entity_type' => 'products',
            'file' => $file,
        ])->assertStatus(403);
    }

    public function test_cross_tenant_import_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $importB = FuelImport::query()->create([
            'company_id' => $companyB->id,
            'entity_type' => 'products',
            'filename' => 'b.csv',
            'status' => FuelImport::STATUS_PREVIEWED,
            'created_by' => $managerB->id,
        ]);

        Sanctum::actingAs($managerA);

        $this->postJson("/api/v1/fuel-station/imports/{$importB->id}/commit")
            ->assertStatus(404);
    }

    public function test_manager_exports_report_csv(): void
    {
        [$company, $manager, $station] = $this->seedTenantWithStation();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/reports/stock/export?station_id='.$station->id)
            ->assertStatus(200)
            ->assertJsonPath('data.filename', fn (string $filename): bool => str_starts_with($filename, 'fuel-stock-'));
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function seedTenantWithOperator(): array
    {
        [$company, $manager] = $this->seedTenant();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        return [$company, $manager, $operator];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: FuelStation}
     */
    private function seedTenantWithStation(): array
    {
        [$company, $manager] = $this->seedTenant();
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return [$company, $manager, $station];
    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $operator;
    }

    private function station(Company $company, string $code = 'ST-01'): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => "Station {$code}",
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/imports', [])->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/imports')->assertStatus(401);
    }

    public function test_operator_cannot_import(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile("code,name,unit_code,status\n"),
        ])->assertStatus(403);
    }

    public function test_dry_run_preview_without_writing(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $csv = "code,name,unit_code,status\nessence,Essence,1,active\ngazole,Gazole,l,active\n";

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($csv),
            'dry_run' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('applied', false)
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonCount(2, 'preview');

        $this->assertDatabaseCount('fuel_products', 0);
        $this->assertDatabaseCount('fuel_imports', 1);
    }

    public function test_valid_import_applies_all_rows(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $csv = "code,name,unit_code,status\nessence,Essence sans plomb,l,active\ngazole,Gazole,l,active\n";

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($csv),
        ])
            ->assertStatus(201)
            ->assertJsonPath('applied', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.valid_lines', 2)
            ->assertJsonPath('data.error_lines', 0);

        $this->assertDatabaseHas('fuel_products', ['company_id' => $this->companyA->id, 'code' => 'essence']);
        $this->assertDatabaseHas('fuel_products', ['company_id' => $this->companyA->id, 'code' => 'gazole']);
        $this->assertDatabaseCount('fuel_products', 2);
    }

    public function test_invalid_row_rolls_back_everything(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Ligne 2 valide, ligne 3 invalide (status inconnu) → zéro écriture.
        $csv = "code,name,unit_code,status\nessence,Essence,l,active\ngazole,Gazole,l,invalid_status\n";

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($csv),
        ])
            ->assertStatus(200)
            ->assertJsonPath('applied', false)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_lines', 1)
            ->assertJsonPath('data.errors.0.line', 3);

        $this->assertDatabaseCount('fuel_products', 0);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        FuelProduct::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'essence',
            'name' => 'Essence',
            'unit_code' => 'l',
            'status' => 'active',
        ]);

        $csv = "code,name,unit_code,status\nessence,Essence,l,active\n";

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($csv),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.error_lines', 1)
            ->assertJsonPath('data.errors.0.errors.0', 'code \'essence\' déjà existant');
    }

    public function test_oversized_file_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // 3 Mo > 2 Mo.
        $big = str_repeat("a,b,c,d\n", 200000);

        $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($big),
        ])->assertStatus(422);
    }

    public function test_import_journal_is_tenant_isolated(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->station($this->companyA);

        $csv = "code,name,unit_code,status\nlubrifiant,Lubrifiant 20W50,l,active\n";

        $import = $this->post('/api/v1/fuel-station/imports', [
            'import_type' => 'products',
            'file' => $this->csvFile($csv),
        ])->assertStatus(201)->json('data');

        // L'import d'un autre tenant n'est pas visible ni lisible.
        Sanctum::actingAs($this->manager($this->companyB));

        $this->getJson('/api/v1/fuel-station/imports')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/fuel-station/imports/{$import['id']}")->assertStatus(404);
    }
}
