<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
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
            'role' => 'manager',
            'manager_role' => 'principal',
            'invited_by_type' => 'super_admin',
        ]);

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
            return $mail->employee->email === 'salim.kaci@nouvelle-societe.dz'
                && $mail->employee->role === 'manager'
                && $mail->employee->manager_role === 'principal'
                && str_contains($mail->activationUrl, '/activate/');
        });
    }

    public function test_platform_mobile_can_create_company_with_minimal_payload(): void
    {
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 7,
            'name' => 'Business',
            'price_monthly' => 149,
            'price_yearly' => 1490,
            'trial_days' => 30,
            'is_active' => true,
        ]);

        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin-mobile@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Mobile Client',
                'country' => 'sn',
                'city' => 'Dakar',
                'email' => 'contact@mobile-client.sn',
                'status' => 'active',
                'manager_first_name' => 'Amina',
                'manager_last_name' => 'Rahmani',
                'manager_email' => 'amina.rahmani@mobile-client.sn',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.company.name', 'Mobile Client')
            ->assertJsonPath('data.company.sector', 'Non precise')
            ->assertJsonPath('data.company.country', 'SN')
            ->assertJsonPath('data.company.plan_id', 7)
            ->assertJsonPath('data.company.currency', 'XOF')
            ->assertJsonPath('data.company.timezone', 'Africa/Dakar')
            ->assertJsonPath('data.company.status', 'active')
            ->assertJsonPath('data.manager.email', 'amina.rahmani@mobile-client.sn');

        DB::statement('SET search_path TO public');

        $this->assertDatabaseHas('companies', [
            'name' => 'Mobile Client',
            'sector' => 'Non precise',
            'country' => 'SN',
            'currency' => 'XOF',
            'timezone' => 'Africa/Dakar',
            'status' => 'active',
            'plan_id' => 7,
        ]);
    }

    public function test_super_admin_can_read_country_defaults_for_mobile_forms(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'country-admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/country-defaults');

        $response->assertOk()
            ->assertJsonPath('data.0.country', 'DZ')
            ->assertJsonFragment([
                'country' => 'SN',
                'currency' => 'XOF',
                'timezone' => 'Africa/Dakar',
                'language' => 'fr',
            ])
            ->assertJsonFragment([
                'country' => 'CM',
                'currency' => 'XAF',
                'timezone' => 'Africa/Douala',
                'language' => 'fr',
            ])
            ->assertJsonFragment([
                'country' => 'TR',
                'currency' => 'TRY',
                'timezone' => 'Europe/Istanbul',
                'language' => 'tr',
            ]);
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
