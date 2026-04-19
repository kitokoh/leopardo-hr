<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyProvisioningTest extends TestCase
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

    public function test_super_admin_can_create_company_and_principal_manager(): void
    {
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Nouvelle Societe',
                'sector' => 'Industrie',
                'country' => 'DZ',
                'city' => 'Oran',
                'email' => 'contact@nouvelle-societe.dz',
                'plan_id' => 1,
                'manager_first_name' => 'Salim',
                'manager_last_name' => 'Kaci',
                'manager_email' => 'salim.kaci@nouvelle-societe.dz',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.company.name', 'Nouvelle Societe');
        $response->assertJsonPath('data.manager.role', 'manager');
        $response->assertJsonPath('data.manager.manager_role', 'principal');
        $response->assertJsonPath('data.company.language', 'fr');

        $this->assertDatabaseHas('companies', [
            'name' => 'Nouvelle Societe',
            'schema_name' => 'shared_tenants',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->assertDatabaseHas('employees', [
            'email' => 'salim.kaci@nouvelle-societe.dz',
            'role' => 'manager',
            'manager_role' => 'principal',
            'company_id' => $response->json('data.company.id'),
        ]);

        DB::statement('SET search_path TO public');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'salim.kaci@nouvelle-societe.dz',
            'invited_by_type' => 'super_admin',
        ]);

        Mail::assertSentCount(1);
    }

    public function test_super_admin_api_login_returns_token(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => $superAdmin->email,
            'password' => 'admin',
            'device_name' => 'tests',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'admin@leopardo-rh.com');
        $response->assertJsonPath('token_type', 'Bearer');
    }
}
