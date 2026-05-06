<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class AuthSelfRegistrationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('employee_id');
                $table->string('company_name');
                $table->string('sector');
                $table->char('country', 2);
                $table->string('city');
                $table->string('manager_name');
                $table->string('manager_id_card')->nullable();
                $table->string('manager_phone')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
    }

    public function test_a_user_can_register_as_an_ordinary_account()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'email', 'role'],
                'token',
            ]);

        $this->assertDatabaseHas('employees', [
            'email' => 'john.doe@example.com',
            'role' => 'ordinary',
        ]);
    }

    public function test_an_ordinary_user_can_submit_a_company_request()
    {
        $employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password_hash' => 'secret',
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/company-requests', [
                'company_name' => 'Acme Corp',
                'sector' => 'Technology',
                'country' => 'DZ',
                'city' => 'Algiers',
                'email' => 'contact@acme-corp.com',
                'phone' => '+213555555555',
                'description' => 'Please approve us.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('company_requests', [
            'company_name' => 'Acme Corp',
        ]);
    }
}
