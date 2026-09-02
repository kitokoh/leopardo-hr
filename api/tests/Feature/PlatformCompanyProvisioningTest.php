<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Mail\UserInvitationMail;
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

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

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

    public function test_provisioning_with_restaurant_solution_activates_feature_flag(): void
    {
        // #6693 — le wizard vitrine « Je suis restaurateur » demande le pack
        // au provisioning : POST /platform/companies avec `solutions:
        // ["restaurant"]` doit activer le feature flag + tracer l'audit.
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 9,
            'name' => 'Restaurant',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-resto@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Resto Chez Ali',
                'sector' => 'restaurant',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'contact@resto-chez-ali.dz',
                'plan_id' => 9,
                'manager_first_name' => 'Ali',
                'manager_last_name' => 'Benali',
                'manager_email' => 'ali.benali@resto-chez-ali.dz',
                'solutions' => ['restaurant'],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.solutions.0.code', 'restaurant');
        $response->assertJsonPath('data.solutions.0.status', 'activated');

        $companyId = $response->json('data.company.id');
        $company = Company::query()->find($companyId);

        $this->assertNotNull($company);
        $this->assertTrue($company->hasFeature('restaurant'), 'Le flag restaurant doit être actif.');

        // Modules transversaux requis par le manifest : actifs par défaut.
        foreach (['rh', 'attendance', 'documents', 'notifications'] as $module) {
            $this->assertTrue($company->hasFeature($module), "{$module} doit être actif par défaut.");
        }

        $this->assertDatabaseHas('shared_tenants.audit_logs', [
            'company_id' => $companyId,
            'action' => 'solution.activated',
        ]);
    }

    public function test_provisioning_rejects_unknown_solution_code(): void
    {
        // #6693 — fail-closed : un code hors allowlist → 422, aucun tenant créé.
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 10,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-unknown@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Bad Solution Co',
                'sector' => 'services',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'contact@bad-solution.dz',
                'plan_id' => 10,
                'manager_first_name' => 'Test',
                'manager_last_name' => 'Test',
                'manager_email' => 'test@bad-solution.dz',
                'solutions' => ['does-not-exist'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.solutions.0', 'Solution sectorielle inconnue : does-not-exist (allowlist SolutionCatalogue).');
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

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-mobile@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

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
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'country-admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

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

    public function test_company_creation_seeds_default_schedule_from_country_rules(): void
    {
        // PA2-COUNTRY-002: choosing a country should not only derive
        // currency/timezone but also seed a default Schedule matching that
        // country's labor-code baseline (e.g. France: 35h/week, Saturday +
        // Sunday rest; Tunisia: 48h/week, Sunday rest only).
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 3,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-schedule@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Societe France',
                'sector' => 'Industrie',
                'country' => 'FR',
                'city' => 'Paris',
                'email' => 'contact@societe-france.fr',
                'plan_id' => 3,
                'manager_first_name' => 'Camille',
                'manager_last_name' => 'Dubois',
                'manager_email' => 'camille.dubois@societe-france.fr',
            ]);

        $response->assertCreated();
        $companyId = $response->json('data.company.id');

        DB::statement('SET search_path TO shared_tenants,public');

        $schedule = DB::table('schedules')
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first();

        DB::statement('SET search_path TO public');

        $this->assertNotNull($schedule, 'Expected a default Schedule to be seeded for the new company.');
        $this->assertSame([6, 7], json_decode($schedule->rest_days, true));
        $this->assertSame([1, 2, 3, 4, 5], json_decode($schedule->work_days, true));
        $this->assertEquals(35.0, (float) $schedule->overtime_threshold_weekly);
    }

    public function test_super_admin_api_login_returns_token(): void
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

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
