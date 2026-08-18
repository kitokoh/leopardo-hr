<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4947 (P0) — la création d'employé via POST /api/v1/employees
 * échouait en 500 sur le vrai schéma : `password_hash` est NOT NULL sans
 * défaut (migration 000101) mais était retiré du payload (`Arr::pull`)
 * avant le `create()` → INSERT sans la colonne → SQLSTATE 23502.
 *
 * Correctif : le modèle est construit complet (fillables + champs sensibles,
 * y compris password_hash) via forceFill AVANT le save — INSERT unique
 * conforme. Ces tests tournent sur `RefreshTenantDatabase` (vraies
 * migrations) pour verrouiller le comportement réel.
 */
class EmployeeCreatePersistsPasswordHashTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_create_employee_with_password_persists_hash_and_allows_login(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Nadia',
            'last_name' => 'Merzougui',
            'email' => 'nadia.merzougui@example.dz',
            'password' => 'Str0ngPass!2026',
            'role' => 'employee',
            'contract_type' => 'CDI',
            'salary_type' => 'fixed',
            'salary_base' => 45000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'nadia.merzougui@example.dz');

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', 'nadia.merzougui@example.dz')
            ->first();

        $this->assertNotNull($employee, 'L\'employé doit exister en base.');
        $this->assertNotNull(
            $employee->password_hash,
            'password_hash doit être persisté (colonne NOT NULL) — #4947.'
        );
        $this->assertTrue(
            Hash::check('Str0ngPass!2026', $employee->password_hash),
            'Le mot de passe fourni doit permettre l\'authentification — #4947.'
        );
    }

    public function test_create_employee_without_password_persists_random_hash(): void
    {
        Mail::fake();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Sofiane',
            'last_name' => 'Haddad',
            'email' => 'sofiane.haddad@example.dz',
            'role' => 'employee',
            'send_invitation' => true,
        ]);

        $response->assertCreated();

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', 'sofiane.haddad@example.dz')
            ->first();

        $this->assertNotNull($employee, 'L\'employé doit exister en base.');
        $this->assertNotNull(
            $employee->password_hash,
            'Un hash aléatoire doit être persisté (colonne NOT NULL) — #4947.'
        );
    }
}
