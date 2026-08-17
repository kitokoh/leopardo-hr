<?php

namespace Tests\Feature\Cabinet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\Cabinet\Domain\Models\CabinetShare;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4798 — la route publique GET /cabinet/shared/{token} ne doit jamais
 * laisser le search_path pointer vers un autre schéma (workers persistants) :
 * résolution tenant-aware + restauration en finally, succès comme 404.
 */
class CabinetShareSearchPathTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_unknown_token_returns_404_and_restores_search_path(): void
    {
        $this->getJson('/api/v1/cabinet/shared/UNKNOWN-TOKEN')
            ->assertStatus(404);

        $this->assertSearchPathRestored();
    }

    public function test_folder_share_returns_json_and_restores_search_path(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $folder = CabinetFolder::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'name' => 'Partage',
        ]);

        CabinetShare::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'shareable_type' => CabinetFolder::class,
            'shareable_id' => $folder->id,
            'share_token' => 'SHARE-TOKEN-001',
            'shared_via' => 'link',
        ]);

        $this->getJson('/api/v1/cabinet/shared/SHARE-TOKEN-001')
            ->assertOk()
            ->assertJsonPath('data.type', 'folder')
            ->assertJsonPath('data.folder.name', 'Partage');

        $this->assertSearchPathRestored();
    }

    public function test_expired_share_returns_410_and_restores_search_path(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        CabinetShare::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'share_token' => 'SHARE-EXPIRED-001',
            'shared_via' => 'link',
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/cabinet/shared/SHARE-EXPIRED-001')
            ->assertStatus(410);

        $this->assertSearchPathRestored();
    }

    private function assertSearchPathRestored(): void
    {
        $row = DB::selectOne('SHOW search_path');
        $this->assertNotNull($row);
        $this->assertSame('shared_tenants,public', $row->search_path);
    }
}
