<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // #5198 : `Company` est qualifié `public.companies` (fix prod). On
        // utilise la table migrée du schéma public — la factory fournit les
        // colonnes strictes (plan_id, subscription_*) — et on ne crée que la
        // table minimale `employees` (modèle non qualifié → shared_tenants).
        \DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');

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
        \DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
        parent::tearDown();
    }

    public function test_employee_scope_only_returns_current_company_rows(): void
    {
        $companyA = Company::factory()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
        ]);

        $companyB = Company::factory()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
        ]);

        app()->instance('current_company', $companyA);
        Employee::query()->forceCreate([
            'email' => 'a.employee@test.local',
            'password_hash' => bcrypt('secret'),
        ]);

        $sensitiveEmployee0 = Employee::withoutGlobalScopes()->forceCreate([
            'email' => 'b.employee@test.local',
            'password_hash' => bcrypt('secret'),
        ]);
        $sensitiveEmployee0->forceFill([
            'company_id' => $companyB->id,
        ])->save();

        $visibleEmails = Employee::query()->pluck('email')->all();

        $this->assertContains('a.employee@test.local', $visibleEmails);
        $this->assertNotContains('b.employee@test.local', $visibleEmails);
    }

    public function test_creating_hook_auto_injects_company_id(): void
    {
        $company = Company::factory()->create([
            'name' => 'Company Main',
            'slug' => 'company-main',
            'sector' => 'atelier',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'main@company.test',
            'status' => 'active',
        ]);

        app()->instance('current_company', $company);

        $employee = new Employee([
            'email' => 'auto.company@test.local',
        ]);
        $employee->forceFill(['password_hash' => bcrypt('secret')])->save();

        $this->assertSame($company->id, $employee->company_id);
    }
}
