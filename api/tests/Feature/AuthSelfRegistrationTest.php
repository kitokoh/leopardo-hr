<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure employees table exists in SQLite memory for this test
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('employees', function ($table) {
                $table->increments('id');
                $table->uuid('company_id')->nullable();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('password_hash');
                $table->string('role')->default('employee');
                $table->string('status')->default('active');
                $table->timestamps();
            });

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

            Schema::create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
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
                'manager_name' => 'John Doe',
                'manager_id_card' => '123456789',
                'manager_phone' => '+213555555555',
                'notes' => 'Please approve us.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('company_requests', [
            'employee_id' => $employee->id,
            'company_name' => 'Acme Corp',
            'manager_name' => 'John Doe',
        ]);
    }
}
