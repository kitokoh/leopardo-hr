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

        $sensitiveEmployee2 = new Employee([
            'email' => 'manager@company.test',
        ]);
        $sensitiveEmployee2->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee2->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

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

        $sensitiveEmployee1 = new Employee([
            'email' => 'employee@company.test',
        ]);
        $sensitiveEmployee1->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee1->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

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

        $sensitiveEmployee0 = new Employee([
            'email' => 'manager@company.test',
        ]);
        $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee0->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ])->save();

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

    public function test_two_fa_account_requires_challenge_on_web_login(): void
    {
        // #6541 — un compte 2FA ne doit plus ouvrir de session web sans code :
        // redirection vers le challenge au lieu de la connexion directe.
        $company = $this->company();
        $employee = $this->manager($company, [
            'two_fa_enabled_at' => now(),
            'two_fa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $token = $this->csrfToken();
        $this->webLogin($employee->email, 'password123', $token)
            ->assertRedirect('/login/2fa');
        $this->assertGuest('web');
        $this->assertNotNull(session('pending_2fa_challenge'));
    }

    public function test_two_fa_challenge_verification_completes_web_login(): void
    {
        $company = $this->company();
        $employee = $this->manager($company, [
            'two_fa_enabled_at' => now(),
            'two_fa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $token = $this->csrfToken();
        $this->webLogin($employee->email, 'password123', $token)
            ->assertRedirect('/login/2fa');

        $challengeToken = (string) session('pending_2fa_challenge');
        $this->assertNotSame('', $challengeToken);

        $challenge = $this->get('/login/2fa');
        $challenge->assertOk();
        $challenge->assertSee('Double authentification');

        $this->from('/login/2fa')->withSession(['_token' => $token])->post('/login/2fa', [
            '_token' => $token,
            'code' => $this->totpCode('JBSWY3DPEHPK3PXP'),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated('web');
        $this->assertNull(session('pending_2fa_challenge'));
    }

    public function test_account_is_locked_after_five_failed_web_logins(): void
    {
        // #6541 — le verrouillage API (5 échecs → 15 min) s'applique aussi à
        // la surface web : au 6e essai, même le bon mot de passe est refusé.
        $company = $this->company();
        $employee = $this->manager($company);

        $token = $this->csrfToken();
        for ($i = 0; $i < 5; $i++) {
            $this->webLogin($employee->email, 'mauvais', $token)->assertRedirect('/login');
        }

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh->getAttributes()['locked_until'] ?? null);
        $this->assertSame(5, (int) $fresh->failed_login_attempts);

        $this->webLogin($employee->email, 'password123', $token)
            ->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_successful_web_login_resets_failed_attempts(): void
    {
        $company = $this->company();
        $employee = $this->manager($company);
        $employee->forceFill(['failed_login_attempts' => 2, 'locked_until' => null])->save();

        $token = $this->csrfToken();
        $this->webLogin($employee->email, 'password123', $token)
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated('web');
        $fresh = $employee->fresh();
        $this->assertSame(0, (int) $fresh->failed_login_attempts);
    }

}
