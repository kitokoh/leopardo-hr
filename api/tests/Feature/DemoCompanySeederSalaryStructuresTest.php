<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\DemoCompanySeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1777 — Le seed démo doit créer des structures salariales : sans
 * `salary_structures`, le moteur de paie ignore tous les employés et la
 * démo « paie en 1 clic » produit 0 bulletin en silence.
 */
class DemoCompanySeederSalaryStructuresTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array{country: string, currency: string}  $config
     * @param  list<int>  $employeeIds
     */
    private function callCreateSalaryStructures(string $companyId, array $config, array $employeeIds): void
    {
        /** @var DemoCompanySeeder $seeder */
        $seeder = app(DemoCompanySeeder::class);
        $method = new \ReflectionMethod(DemoCompanySeeder::class, 'createSalaryStructures');
        $method->invoke($seeder, $companyId, $config, $employeeIds);
    }

    public function test_demo_seed_creates_salary_structure_per_active_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'schema_name' => 'shared_tenants',
        ]);

        $employeeIds = [];
        foreach ([
            ['email' => 'a@demo.dz', 'salary_base' => 60000],
            ['email' => 'b@demo.dz', 'salary_base' => 45000],
            ['email' => 'inactive@demo.dz', 'salary_base' => 30000],
        ] as $idx => $spec) {
            $employeeId = DB::table('shared_tenants.employees')->insertGetId([
                'company_id' => $company->id,
                'first_name' => 'Emp',
                'last_name' => (string) $idx,
                'email' => $spec['email'],
                'password_hash' => 'x',
                'salary_base' => $spec['salary_base'],
                'status' => $spec['email'] === 'inactive@demo.dz' ? 'archived' : 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $employeeIds[] = $employeeId;
        }

        // Seuls les 2 employés actifs reçoivent une structure.
        $this->callCreateSalaryStructures(
            (string) $company->id,
            ['country' => 'DZ', 'currency' => 'DZD'],
            [0 => $employeeIds[0], 1 => $employeeIds[1]]
        );

        $structures = DB::table('shared_tenants.salary_structures')
            ->where('company_id', $company->id)
            ->get();

        $this->assertCount(2, $structures);
        $this->assertNotNull($structures[0]);
        $this->assertNotNull($structures[1]);
        $this->assertSame(60000.0, (float) $structures[0]->base_salary);
        $this->assertSame(45000.0, (float) $structures[1]->base_salary);
        $this->assertSame('DZ', $structures[0]->country_code);

        // Chaque employé actif est affecté à sa structure.
        $assigned = DB::table('shared_tenants.employees')
            ->where('company_id', $company->id)
            ->pluck('salary_structure_id', 'email');

        $this->assertNotNull($assigned['a@demo.dz']);
        $this->assertNotNull($assigned['b@demo.dz']);
        $this->assertNull($assigned['inactive@demo.dz']);

        // Composant prime de rendement présent.
        $components = DB::table('shared_tenants.salary_components')
            ->where('company_id', $company->id)
            ->get();
        $this->assertCount(2, $components);
        $this->assertNotNull($components[0]);
        $this->assertSame('PRIME_PERF', $components[0]->code);
        $this->assertSame(5.0, (float) $components[0]->percentage);
    }

    public function test_demo_seed_skips_salary_structures_when_no_active_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'schema_name' => 'shared_tenants',
        ]);

        $this->callCreateSalaryStructures((string) $company->id, ['country' => 'DZ', 'currency' => 'DZD'], []);

        $this->assertSame(0, DB::table('shared_tenants.salary_structures')
            ->where('company_id', $company->id)
            ->count());
    }
}
