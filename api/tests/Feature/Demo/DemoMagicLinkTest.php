<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2253 — Magic link d'accès au sandbox de démo.
 *
 * GET /demo-login/{token} : jeton à usage unique (hash SHA-256 + expiration
 * dans employees.extra_data) → session web + redirection dashboard. Jeton
 * invalide/expiré → retour au login avec erreur explicite.
 */
class DemoMagicLinkTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createDemoManager(array $extraData): Employee
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => 'demo-'.uniqid().'@leopardo.test',
            'extra_data' => $extraData,
        ]);

        return $manager;
    }

    public function test_valid_magic_link_logs_in_and_redirects_to_dashboard(): void
    {
        $token = 'demo-token-'.bin2hex(random_bytes(16));
        $manager = $this->createDemoManager([
            'demo_access_token_hash' => hash('sha256', $token),
            'demo_access_token_expires_at' => now()->addHours(24)->toIso8601String(),
        ]);

        $response = $this->get('/demo-login/'.$token);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('web');
        $this->assertSame($manager->id, auth('web')->id());

        // Jeton à usage unique : le hash a été révoqué.
        $manager->refresh();
        $this->assertArrayNotHasKey('demo_access_token_hash', $manager->extra_data);
    }

    public function test_invalid_magic_link_redirects_to_login_with_error(): void
    {
        $this->createDemoManager([
            'demo_access_token_hash' => hash('sha256', 'autre-token'),
            'demo_access_token_expires_at' => now()->addHours(24)->toIso8601String(),
        ]);

        $response = $this->get('/demo-login/mauvais-token');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_expired_magic_link_redirects_to_login_with_error(): void
    {
        $token = 'demo-token-expire-'.bin2hex(random_bytes(8));
        $this->createDemoManager([
            'demo_access_token_hash' => hash('sha256', $token),
            'demo_access_token_expires_at' => now()->subMinutes(5)->toIso8601String(),
        ]);

        $response = $this->get('/demo-login/'.$token);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }
}
