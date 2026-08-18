<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedInteger('employee_id')->nullable();
                $table->string('company_name');
                $table->string('sector')->nullable();
                $table->char('country', 2)->nullable();
                $table->string('city')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('description')->nullable();
                $table->string('manager_name')->nullable();
                $table->string('manager_id_card')->nullable();
                $table->string('manager_phone')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('pending');
                $table->uuid('approved_company_id')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createInvitation(string $email, ?string $token = 'valid-token-123'): string
    {
        // Issue #2617 : l'inscription nécessite une invitation valide.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        DB::table('public.user_invitations')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 0,
            'email' => $email,
            'role' => 'ordinary',
            'manager_role' => null,
            'invited_by_type' => 'platform',
            'invited_by_email' => 'admin@leopardo-rh.com',
            'token_hash' => hash('sha256', (string) $token),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (string) $token;
    }

    public function test_registration_requires_a_valid_invitation(): void
    {
        // Sans invitation : inscription refusée (fail-closed, #2617).
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_token' => 'missing-token',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $this->assertDatabaseMissing('employees', ['email' => 'john.doe@example.com']);
    }

    public function test_a_user_can_register_with_a_valid_invitation(): void
    {
        $token = $this->createInvitation('john.doe@example.com');

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_token' => $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'email', 'role'],
                'token',
            ]);

        // L'employé est rattaché au company_id de l'invitation (plus
        // d'employé sans tenant) et l'invitation est consommée.
        $employee = Employee::query()->where('email', 'john.doe@example.com')->firstOrFail();
        $this->assertNotNull($employee->company_id);

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'john.doe@example.com',
        ]);
        $this->assertNotNull(
            DB::table('public.user_invitations')->where('email', 'john.doe@example.com')->value('accepted_at')
        );
    }

    public function test_an_ordinary_user_can_submit_a_company_request()
    {
        $employee = Employee::forceCreate([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password_hash' => 'secret',
        ]);
        $employee->role = 'ordinary';
        $employee->status = 'active';
        $employee->save();

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
