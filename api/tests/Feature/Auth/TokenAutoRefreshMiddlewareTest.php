<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #5581 — TokenAutoRefreshMiddleware : rotation atomique + token dans le
 * corps JSON.
 *
 * Avant : le nouveau token partait dans le header `X-New-Token` (capturable
 * par tout intermédiaire qui loggue les headers) et la rotation n'était pas
 * atomique (deux requêtes concurrentes → deux tokens valides).
 * Après : token dans le corps JSON (`token`/`token_type`/`token_expires_at`
 * + `token_refreshed`), header `X-New-Token` supprimé, ancien token révoqué.
 */
class TokenAutoRefreshMiddlewareTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_auto_refresh_returns_new_token_in_body_not_in_header(): void
    {
        // Fenêtre de rafraîchissement > expiration → la rotation se déclenche
        // dès la première requête (threshold dans le passé).
        config()->set('sanctum.expiration', 60);
        config()->set('sanctum.auto_refresh_window', 100000);

        $token = $this->employee->createToken('tests', ['*']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('token_refreshed', true);
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('Bearer', $response->json('token_type'));

        // #5581 — plus jamais de token dans un header.
        $response->assertHeaderMissing('X-New-Token');
        // Signal non sensible conservé.
        $response->assertHeader('X-Token-Refreshed', 'true');
    }

    public function test_old_token_is_revoked_after_auto_refresh(): void
    {
        config()->set('sanctum.expiration', 60);
        config()->set('sanctum.auto_refresh_window', 100000);

        $token = $this->employee->createToken('tests', ['*']);

        $first = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me');
        $first->assertOk();
        $newToken = $first->json('token');
        $this->assertNotEmpty($newToken);

        // Le guard Sanctum (RequestGuard) cache l'utilisateur entre les
        // requêtes d'un même test : on le purge pour que la 2e requête
        // re-valide réellement le token (artefact de test Laravel).
        app('auth')->forgetGuards();

        // Le token d'origine est supprimé (single-use) → 401.
        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // Le nouveau token fonctionne.
        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_no_rotation_outside_refresh_window(): void
    {
        // Fenêtre courte vs expiration : le token est « frais », pas de rotation.
        config()->set('sanctum.expiration', 10080);
        config()->set('sanctum.auto_refresh_window', 1440);

        $token = $this->employee->createToken('tests', ['*']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertNull($response->json('token'));
        $response->assertHeaderMissing('X-Token-Refreshed');
        $response->assertHeaderMissing('X-New-Token');
    }
}
