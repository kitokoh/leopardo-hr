<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
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
            $table->jsonb('features')->default(\DB::raw("'{}'::jsonb"));
            $table->jsonb('metadata')->default(\DB::raw("'{}'::jsonb"));
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
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');
        parent::tearDown();
    }

    public function test_employee_scope_only_returns_current_company_rows(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        app()->instance('current_company', $companyA);
        Employee::query()->create([
            'email' => 'a.employee@test.local',
            'password_hash' => bcrypt('secret'),
        ]);

        Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'b.employee@test.local',
            'password_hash' => bcrypt('secret'),
        ]);

        $visibleEmails = Employee::query()->pluck('email')->all();

        $this->assertContains('a.employee@test.local', $visibleEmails);
        $this->assertNotContains('b.employee@test.local', $visibleEmails);
    }

    public function test_creating_hook_auto_injects_company_id(): void
    {
        $company = Company::query()->create([
            'name' => 'Company Main',
            'slug' => 'company-main',
            'sector' => 'atelier',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'main@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        app()->instance('current_company', $company);

        $employee = Employee::query()->create([
            'email' => 'auto.company@test.local',
            'password_hash' => bcrypt('secret'),
        ]);

        $this->assertSame($company->id, $employee->company_id);
    }
}
