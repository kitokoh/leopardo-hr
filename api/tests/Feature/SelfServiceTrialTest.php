<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Mail\TrialWelcomeMail;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SelfServiceTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_provision_trial_tenant_successfully()
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@newtech.dz',
            'company' => 'NewTech Algeria',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Votre espace Leopardo est prêt !',
            ])
            ->assertJsonStructure([
                'data' => [
                    'company' => ['id', 'name', 'slug'],
                    'manager' => ['email', 'first_name', 'last_name', 'temp_password'],
                    'trial' => ['days', 'ends_at'],
                ],
            ]);

        // Verify company created
        $companyId = $response->json('data.company.id');
        $company = Company::find($companyId);
        $this->assertNotNull($company);
        $this->assertEquals('NewTech Algeria', $company->name);
        $this->assertEquals('shared_tenants', $company->schema_name);
        $this->assertEquals('trial', $company->status);
        $this->assertEquals('fr', $company->language); // From DZ defaults

        // Verify manager created in the tenant
        // Switch to the tenant DB context to check employee
        app(TenantManager::class)->setTenant($company);

        $manager = Employee::where('email', 'founder@newtech.dz')->first();
        $this->assertNotNull($manager);
        $this->assertEquals('principal', $manager->manager_role);
        $this->assertEquals('Founder', $manager->first_name); // Auto-extracted
        $this->assertEquals('Newtech.dz', $manager->last_name);

        app(TenantManager::class)->resetToPrevious();

        // Verify CRM record created in public schema
        $this->assertDatabaseHas('company_requests', [
            'email' => 'founder@newtech.dz',
            'company_name' => 'NewTech Algeria',
            'status' => 'approved',
            'approved_company_id' => $company->id,
        ]);

        // Verify Mail sent
        Mail::assertSent(TrialWelcomeMail::class, function ($mail) {
            return $mail->hasTo('founder@newtech.dz');
        });
    }

    public function test_rejects_duplicate_manager_email()
    {
        // First provision
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'First Company',
        ])->assertStatus(201);

        // Try again with same email
        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'Second Company',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => 'EMAIL_ALREADY_REGISTERED',
            ]);
    }

    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/trial/signup', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'company']);
    }
}
