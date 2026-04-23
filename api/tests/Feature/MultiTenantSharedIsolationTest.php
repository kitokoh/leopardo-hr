<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
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

        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('sector');
            $table->char('country', 2);
            $table->string('city');
            $table->string('email');
            $table->unsignedInteger('plan_id')->nullable();
            $table->string('schema_name', 63);
            $table->string('tenancy_type', 20)->default('shared');
            $table->string('status', 20)->default('active');
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->char('language', 2)->default('fr');
            $table->string('timezone', 50)->default('Africa/Algiers');
            $table->char('currency', 3)->default('DZD');
            $table->jsonb('features')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();
        });

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

        $cities = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Setif'];
        foreach ($cities as $i => $city) {
            $this->companies[] = Company::query()->create([
                'name' => "Company {$city}",
                'slug' => 'company-'.strtolower($city),
                'sector' => 'restaurant',
                'country' => 'DZ',
                'city' => $city,
                'email' => strtolower($city).'@company.test',
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'active',
            ]);
        }
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');
        parent::tearDown();
    }

    public function test_five_shared_companies_each_only_see_their_own_employees(): void
    {
        foreach ($this->companies as $company) {
            app()->instance('current_company', $company);
            Employee::query()->create([
                'email' => "rh@{$company->slug}.test",
                'password_hash' => Hash::make('secret'),
                'role' => 'manager',
                'manager_role' => 'rh',
            ]);
            Employee::query()->create([
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
        Employee::query()->create([
            'email' => 'rh@leak-a.test',
            'password_hash' => Hash::make('secret'),
        ]);

        // Bascule de contexte : les creations suivantes appartiennent a B.
        app()->instance('current_company', $companyB);
        Employee::query()->create([
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
        Employee::query()->create([
            'email' => 'suspended@a.test',
            'password_hash' => Hash::make('secret'),
        ]);

        $this->assertSame(
            ['suspended@a.test'],
            Employee::query()->pluck('email')->all()
        );
    }
}
