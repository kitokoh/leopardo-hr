<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * POST /api/v1/company-requests — les bornes de validation doivent refléter
 * le schéma réel de `company_requests`.
 *
 * Bug d'origine (audit des écritures) : `country` était validé en `max:100`
 * alors que la colonne est `char(2)`. Une valeur parfaitement légitime comme
 * "Algeria" passait la validation puis faisait échouer l'INSERT :
 *
 *   SQLSTATE[22001]: String data, right truncated:
 *   value too long for type character(2)
 *
 * → 500 INTERNAL_ERROR au lieu d'un 422 explicite. Même classe de défaut
 * pour `company_name` (colonne varchar(100), validation max:200).
 *
 * Ces tests échouent sur le code d'avant le correctif et passent après.
 */
class CompanyRequestStoreSchemaBoundsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Même patron que test_an_ordinary_user_can_submit_a_company_request :
        // la route /company-requests est protégée par `auth:sanctum` (guard
        // par défaut = employé). On ne touche PAS au search_path ici :
        // `employees` vit dans `shared_tenants` et `company_requests` dans
        // `public` — le search_path par défaut du TestCase (`shared_tenants,public`)
        // résout les deux.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'nadia.bounds@example.com',
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        $this->employee = $employee;

        $this->actingAs($this->employee, 'sanctum');
    }

    protected function tearDown(): void
    {
        DB::table('company_requests')->where('email', 'like', '%@bounds.dz')->delete();

        parent::tearDown();
    }

    /**
     * Le cas qui produisait un 500 : un nom de pays en clair.
     */
    public function test_country_longer_than_two_chars_is_rejected_with_422_not_500(): void
    {
        $response = $this->postJson('/api/v1/company-requests', [
            'company_name' => 'Terrain Plus',
            'sector' => 'BTP',
            'country' => 'Algeria',
            'city' => 'Alger',
            'email' => 'contact@bounds.dz',
            'phone' => '0550000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['country']);

        $this->assertDatabaseMissing('company_requests', ['email' => 'contact@bounds.dz']);
    }

    /**
     * `company_name` est varchar(100) : 150 caractères doivent être refusés
     * en 422, pas provoquer un 500 au moment de l'INSERT.
     */
    public function test_company_name_longer_than_column_is_rejected_with_422_not_500(): void
    {
        $response = $this->postJson('/api/v1/company-requests', [
            'company_name' => str_repeat('A', 150),
            'sector' => 'BTP',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'long@bounds.dz',
            'phone' => '0550000001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_name']);

        $this->assertDatabaseMissing('company_requests', ['email' => 'long@bounds.dz']);
    }

    /**
     * Le chemin nominal doit continuer de fonctionner, et le code pays est
     * normalisé en majuscules (char(2) = ISO-3166 alpha-2).
     */
    public function test_valid_request_is_created_and_country_is_uppercased(): void
    {
        $response = $this->postJson('/api/v1/company-requests', [
            'company_name' => 'Terrain Plus',
            'sector' => 'BTP',
            'country' => 'dz',
            'city' => 'Alger',
            'email' => 'ok@bounds.dz',
            'phone' => '0550000002',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.company_name', 'Terrain Plus');

        $this->assertDatabaseHas('company_requests', [
            'email' => 'ok@bounds.dz',
            'country' => 'DZ',
        ]);
    }
}
