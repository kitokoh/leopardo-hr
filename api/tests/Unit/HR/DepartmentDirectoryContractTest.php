<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Infrastructure\Services\DepartmentDirectoryAdapter;
use App\Shared\Contracts\HR\DepartmentDirectory;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-023 cycle 3 (#8211) — contrat partagé d'annuaire des départements :
 * le container résout `DepartmentDirectory` vers l'adapter HR, et la requête
 * reprend à l'identique le comportement historique d'`AttendanceReportService`
 * (filtre `company_id`, projection `pluck('name', 'id')`) — isolation tenant
 * incluse : aucun département d'une autre entreprise ne remonte.
 */
class DepartmentDirectoryContractTest extends TestCase
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

    public function test_contract_resolves_to_hr_adapter(): void
    {
        $this->assertInstanceOf(
            DepartmentDirectoryAdapter::class,
            $this->app->make(DepartmentDirectory::class),
        );
    }

    public function test_names_by_company_returns_id_name_map_scoped_to_company(): void
    {
        $companyA = $this->seedCompany('company-a', 'a@company.test');
        $companyB = $this->seedCompany('company-b', 'b@company.test');

        $direction = Department::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Direction',
        ]);
        $cuisine = Department::query()->forceCreate([
            'company_id' => $companyA->id,
            'name' => 'Cuisine',
        ]);
        // Un département d'une autre entreprise ne doit jamais remonter.
        Department::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Salle',
        ]);

        $names = $this->app->make(DepartmentDirectory::class)
            ->namesByCompany($companyA->id);

        // Map id => name limitée à l'entreprise demandée (isolation tenant).
        $this->assertCount(2, $names);
        $this->assertSame('Direction', $names[$direction->id]);
        $this->assertSame('Cuisine', $names[$cuisine->id]);
    }

    private function seedCompany(string $slug, string $email): Company
    {
        return Company::query()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $email,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
    }
}
