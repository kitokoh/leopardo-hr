<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class GlobalEmailUniquenessTest extends TestCase
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

    public function test_cannot_create_employee_with_email_already_used_in_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal', 'manager@companya.com');
        $managerA->syncUserLookup();

        $managerB = $this->createEmployee($companyB, 'manager', 'principal', 'manager@companyb.com');

        $response = $this->actingAs($managerB, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Attacker',
                'last_name' => 'User',
                'email' => 'manager@companya.com',
                'password' => 'password123',
                'role' => 'employee',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        $errors = $response->json('errors.email');
        $this->assertTrue(
            in_array('La valeur du champ email est déjà utilisée.', $errors) ||
            in_array('Cet email est déjà utilisé par un utilisateur sur la plateforme (GLOBAL_COLLISION).', $errors),
            'Expected email uniqueness error, got: '.json_encode($errors)
        );
    }

    public function test_sync_user_lookup_is_protected_against_hijacking(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        // Manually setup a lookup for victim in Company A
        DB::table('user_lookups')->insert([
            'email' => 'victim@test.com',
            'company_id' => $companyA->id,
            'schema_name' => 'schema_a',
            'employee_id' => 123,
            'role' => 'employee',
        ]);

        // Create an employee in Company B with a different email
        $attacker = $this->createEmployee($companyB, 'employee', null, 'attacker@test.com');

        // Force the email to be the victim's email and attempt to sync
        $attacker->email = 'victim@test.com';
        $attacker->syncUserLookup();

        // AFTER THE FIX:
        // The lookup for victim@test.com should STILL point to Company A
        $lookup = DB::table('user_lookups')->where('email', 'victim@test.com')->first();
        $this->assertEquals($companyA->id, $lookup->company_id);
        $this->assertEquals(123, $lookup->employee_id);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null, ?string $email = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => $email ?? strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        return $employee;
    }
}
