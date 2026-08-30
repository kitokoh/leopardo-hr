<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API imports CSV sécurisés FuelStation — FUEL-018 (issue #5812).
 *
 * Couvre : RBAC (employé 403), preview dry-run sans écriture, import
 * produits appliqué, rollback logique (ligne invalide → zéro écriture),
 * limites (fichier trop gros), journal d'import audité, cross-tenant 404.
 */
class FuelImportApiTest extends TestCase
{
    use RefreshTenantDatabase;

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
