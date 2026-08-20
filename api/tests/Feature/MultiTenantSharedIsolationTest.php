<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Multi-tenant N>1 : 5 compagnies dans le meme schema `shared_tenants`.
 *
 * Ce test vaut pour la regression : on augmente le nombre de tenants shared
 * (cas dominant du MVP) pour verifier que le `BelongsToCompany` scope global
 * est toujours strict, et que les requetes auto-portees a une compagnie ne
 * fuitent jamais vers les 4 autres.
 *
 * Il complete `TenantIsolationTest` (2 compagnies) et `EstimationServiceTest`
 * (isolation des estimations).
 */
class MultiTenantSharedIsolationTest extends TestCase
{
    /**
     * @var list<Company>
     */
    private array $companies = [];

    protected function setUp(): void
    {
        parent::setUp();

        // #5198 : `Company` est qualifié `public.companies` (fix prod). On
        // utilise la table migrée du schéma public (factory → colonnes
        // strictes) et on ne crée que la table minimale `employees`
        // (modèle non qualifié → shared_tenants).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
        } else {
            Schema::dropIfExists('employees');
        }

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id');
            $table->string('matricule', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 20)->default('employee');
            $table->string('manager_role', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // La table publique persiste entre les méthodes : purge des slugs de
        // ce test avant re-création (contrainte companies_slug_unique).
        $this->purgeTestCompanies();

        $cities = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Setif'];
        foreach ($cities as $i => $city) {
            /** @var Company $company */
            $company = Company::factory()->create([
                'name' => "Company {$city}",
                'slug' => 'company-'.strtolower($city),
                'sector' => 'restaurant',
                'country' => 'DZ',
                'city' => $city,
                'email' => strtolower($city).'@company.test',
            ]);
            $this->companies[] = $company;
        }
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
        } else {
            Schema::dropIfExists('employees');
        }
        $this->purgeTestCompanies();
        parent::tearDown();
    }

    private function purgeTestCompanies(): void
    {
        $slugs = ['company-alger', 'company-oran', 'company-constantine', 'company-annaba', 'company-setif'];
        DB::table('public.companies')->whereIn('slug', $slugs)->delete();
    }

    public function test_five_shared_companies_each_only_see_their_own_employees(): void
    {
        foreach ($this->companies as $company) {
            app()->instance('current_company', $company);
            $sensitiveEmployee0 = new Employee([
                'email' => "rh@{$company->slug}.test",
            ]);
            $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('secret')])->save();
            $sensitiveEmployee0->forceFill([
                'role' => 'manager',
                'manager_role' => 'rh',
            ])->save();
            Employee::query()->forceCreate([
                'email' => "emp@{$company->slug}.test",
                'password_hash' => Hash::make('secret'),
            ]);
        }

        // Chaque compagnie ne voit que ses 2 employes, jamais ceux des autres.
        foreach ($this->companies as $company) {
            app()->instance('current_company', $company);
            $visible = Employee::query()->pluck('email')->all();

            $this->assertCount(2, $visible, "Company {$company->slug} should see exactly 2 employees");
            $this->assertContains("rh@{$company->slug}.test", $visible);
            $this->assertContains("emp@{$company->slug}.test", $visible);

            foreach ($this->companies as $other) {
                if ($other->id === $company->id) {
                    continue;
                }
                $this->assertNotContains("rh@{$other->slug}.test", $visible);
                $this->assertNotContains("emp@{$other->slug}.test", $visible);
            }
        }

        // Sanity check global : 10 employes au total dans shared_tenants.
        $this->assertSame(10, Employee::withoutGlobalScopes()->count());
    }

    public function test_creating_from_wrong_context_never_leaks_rows(): void
    {
        $companyA = $this->companies[0];
        $companyB = $this->companies[1];

        app()->instance('current_company', $companyA);
        Employee::query()->forceCreate([
            'email' => 'rh@leak-a.test',
            'password_hash' => Hash::make('secret'),
        ]);

        // Bascule de contexte : les creations suivantes appartiennent a B.
        app()->instance('current_company', $companyB);
        Employee::query()->forceCreate([
            'email' => 'rh@leak-b.test',
            'password_hash' => Hash::make('secret'),
        ]);

        // B ne voit jamais l'employe cree sous A.
        $this->assertSame(
            ['rh@leak-b.test'],
            Employee::query()->pluck('email')->all()
        );

        // A, restaure, ne voit que le sien.
        app()->instance('current_company', $companyA);
        $this->assertSame(
            ['rh@leak-a.test'],
            Employee::query()->pluck('email')->all()
        );
    }

    public function test_suspended_company_is_still_visible_in_its_own_scope(): void
    {
        // Un tenant suspendu (plan echu) doit etre isole comme les autres ;
        // c'est la couche auth (WebAuthController) qui bloque le login.
        $companyA = $this->companies[0];
        $companyA->update(['status' => 'suspended']);

        app()->instance('current_company', $companyA);
        Employee::query()->forceCreate([
            'email' => 'suspended@a.test',
            'password_hash' => Hash::make('secret'),
        ]);

        $this->assertSame(
            ['suspended@a.test'],
            Employee::query()->pluck('email')->all()
        );
    }
}
