<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA 2026-08-15 (#2652) — le login ne doit jamais renvoyer un 500 brut
 * `{"message":"Server Error"}` quelle que soit la qualité des données
 * (password_hash null, locked_until invalide…). Constat live : les comptes
 * tenant existants en prod répondaient 500 au lieu de 401.
 */
class AuthLoginHardeningTest extends TestCase
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

    private function makeCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Company Hardening',
            'slug' => 'company-hardening-'.uniqid(),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'hardening@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    public function test_login_with_broken_password_hash_returns_401_not_500(): void
    {
        $company = $this->makeCompany();

        // #2973 : la colonne employees.password_hash est NOT NULL (migration
        // 2026_04_01_000101) — un hash null est impossible à insérer, le test
        // initial (#2838) échouait au setup (Not null violation). On simule
        // l'état legacy équivalent : un hash corrompu/absent sémantiquement
        // (invité SSO, seed incomplet) → Hash::check ne doit pas lever
        // (TypeError → 500) mais renvoyer 401.
        $createdEmployee = Employee::query()->create([
            'email' => 'broken-hash@company.test',
            'password_hash' => 'legacy-broken-hash',
        ]);
            $createdEmployee->company_id = $company->id;
            $createdEmployee->role = 'employee';
            $createdEmployee->status = 'active';
            $createdEmployee->save();


        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'broken-hash@company.test',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'INVALID_CREDENTIALS');
        $response->assertJsonStructure(['error', 'message', 'localized_message']);
    }

    public function test_login_empty_password_hash_returns_401_not_500(): void
    {
        $company = $this->makeCompany();

        $createdEmployee = Employee::query()->create([
            'email' => 'empty-hash@company.test',
            'password_hash' => '',
        ]);
            $createdEmployee->company_id = $company->id;
            $createdEmployee->role = 'employee';
            $createdEmployee->status = 'active';
            $createdEmployee->save();


        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'empty-hash@company.test',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'INVALID_CREDENTIALS');
    }

    public function test_login_wrong_password_returns_contract_shape(): void
    {
        $company = $this->makeCompany();

        $createdEmployee = Employee::query()->create([
            'email' => 'good-hash@company.test',
            'password_hash' => Hash::make('password123'),
        ]);
            $createdEmployee->company_id = $company->id;
            $createdEmployee->role = 'employee';
            $createdEmployee->status = 'active';
            $createdEmployee->save();


        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'good-hash@company.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_CREDENTIALS');
        $response->assertJsonPath('message', 'INVALID_CREDENTIALS');
        $this->assertNotNull($response->json('localized_message'));
    }

    public function test_login_unknown_email_still_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@nowhere.test',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }
}
