<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class WebAuthPagesTest extends TestCase
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

    public function test_guest_is_redirected_to_login_for_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_manager_can_login_to_web_dashboard(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'manager@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('web');
    }

    public function test_employee_is_redirected_to_personal_space_on_web_login(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->from('/login')->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'employee@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/me');
        $this->assertAuthenticated('web');
    }

    public function test_suspended_company_is_blocked_from_web_login(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'suspended',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->from('/login')->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'manager@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_login_page_renders_accessible_manager_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Connexion manager');

        // S-6 a11y : labels programmatiquement associés (for/id). Sans cette
        // association, les champs sont « sans nom accessible » pour les
        // lecteurs d'écran et les tests E2E getByLabel() échouent
        // (régression staging 2026-08-13, run #31653999170).
        $response->assertSee('<label for="email"', false);
        $response->assertSee('<label for="password"', false);
        $response->assertSee('id="email"', false);
        $response->assertSee('id="password"', false);
    }

    public function test_platform_login_page_renders_accessible_form(): void
    {
        $response = $this->get('/platform/login');

        $response->assertOk();
        $response->assertSee('Connexion plateforme');
        $response->assertSee('<label for="email"', false);
        $response->assertSee('<label for="password"', false);
        $response->assertSee('id="email"', false);
        $response->assertSee('id="password"', false);
    }

    public function test_failed_login_renders_inline_error_with_aria_wiring(): void
    {
        // Vrai flux HTTP (même pattern multi-requêtes que OnboardingE2ETest :
        // le driver de session array persiste entre les requêtes du test).
        $this->get('/login');
        $token = session()->token();

        $this->from('/login')->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'inexistant@company.test',
            'password' => 'mauvais-mot-de-passe',
        ])->assertRedirect('/login');

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Identifiants invalides.');
        $response->assertSee('id="email-error"', false);
        $response->assertSee('role="alert"', false);
        $response->assertSee('aria-invalid="true"', false);
        $response->assertSee('aria-describedby="email-error"', false);
    }
}

