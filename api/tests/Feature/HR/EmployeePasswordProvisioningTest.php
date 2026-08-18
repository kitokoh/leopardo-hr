<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Vague QA 2026-08-14 — régression : le mot de passe fourni à la création
 * d'un employé (POST /employees) était SILENCIEUSEMENT ignoré.
 *
 * Cause racine : `CreateEmployeeDTO::toArray()` n'exposait pas `password` →
 * `EmployeeService::create()` hashait toujours un mot de passe aléatoire
 * (Str::random(32)) et déclenchait une invitation email (jamais délivrée,
 * provider audit-only #2257) → l'employé ne pouvait jamais se connecter
 * avec le mot de passe choisi par le manager.
 */
class EmployeePasswordProvisioningTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_employee_can_login_with_password_provided_at_creation(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Acme QA',
            'slug' => 'acme-qa',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'company@acme-qa.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager, [], 'sanctum');

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Nadia',
            'last_name' => 'Kerrouche',
            'email' => 'nadia.kerrouche@acme-qa.test',
            'role' => 'employee',
            'password' => 'ProvidedPass123!',
            'gross_salary' => 60000,
            'country' => 'DZ',
        ]);

        $response->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');

        /** @var Employee|null $employee */
        $employee = Employee::query()->where('email', 'nadia.kerrouche@acme-qa.test')->first();
        $this->assertNotNull($employee);
        $this->assertTrue(
            Hash::check('ProvidedPass123!', (string) $employee->password_hash),
            'Le mot de passe fourni doit être hashé tel quel (pas un aléatoire).'
        );

        // Aucune invitation email : le mot de passe fourni suffit.
        Mail::assertNothingSent();

        // L'employé peut se connecter immédiatement avec ce mot de passe.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'nadia.kerrouche@acme-qa.test',
            'password' => 'ProvidedPass123!',
            'device_name' => 'qa-test',
        ]);
        $login->assertOk();
        $this->assertNotEmpty($login->json('token'));
    }
}
