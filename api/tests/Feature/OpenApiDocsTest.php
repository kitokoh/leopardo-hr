<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use Tests\TestCase;

class OpenApiDocsTest extends TestCase
{
    public function test_swagger_ui_page_is_public(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Leopardo RH API Docs')
            ->assertSee('/docs/openapi.yaml');
    }

    public function test_openapi_yaml_is_served_from_the_canonical_spec(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: "3.0.3"', false)
            ->assertSee('Leopardo RH API', false)
            ->assertSee('https://gestionemployerbackend.onrender.com/api/v1', false)
            ->assertSee('Error429', false)
            ->assertSee('TWO_FA_REQUIRED', false);
    }

    public function test_root_exposes_tester_guide_and_api_explorer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Guide Testeur')
            ->assertSee('/tester-guide')
            ->assertSee('API Explorer')
            ->assertSee('/api-explorer');

        $this->get('/tester-guide')
            ->assertOk()
            ->assertSee('Guide testeur Leopardo RH')
            ->assertSee('Application mobile')
            ->assertSee('Admin plateforme');

        $this->get('/api-explorer')
            ->assertOk()
            ->assertSee('API Explorer Leopardo RH')
            ->assertSee('Developer preview')
            ->assertSee('Sandbox Render')
            ->assertSee('Authorization: Bearer')
            ->assertSee('Webhooks')
            ->assertSee('/demo-users')
            ->assertSee('/notifications')
            ->assertSee('/device-tokens');
    }

    /**
     * PA2-API-007 - the API Explorer must expose sandbox code snippets in
     * curl, JavaScript and PHP so developers can copy a ready-to-run request
     * outside the browser tool.
     */
    public function test_api_explorer_exposes_curl_javascript_and_php_sandbox_snippets(): void
    {
        $this->get('/api-explorer')
            ->assertOk()
            ->assertSee('Exemples de code (sandbox)')
            ->assertSee('curl', false)
            ->assertSee('JavaScript (fetch)')
            ->assertSee('PHP', false)
            ->assertSee('snippetOutput', false)
            ->assertSee('buildCurlSnippet', false)
            ->assertSee('buildJavaScriptSnippet', false)
            ->assertSee('buildPhpSnippet', false)
            // Non-régression #2265 : le littéral `<?php` du snippet builder est
            // échappé dans la vue (tag court PHP ouvert puis fermé) ; le HTML
            // rendu doit contenir la séquence brute (assertSee(..., false) car
            // le défaut chercherait la forme échappée `&lt;?php`).
            ->assertSee('<?php', false)
            ->assertSee('GuzzleHttp', false)
            ->assertSee('Copier');
    }

    /**
     * Issue #5588 : en production, la documentation API n'est plus publique —
     * la Gate `viewApiDocs` (utilisateur tenant authentifié) est exigée.
     * Hors production (dev/staging/test), la doc reste ouverte.
     */
    public function test_docs_require_authentication_in_production(): void
    {
        // #5588 : config()->set n'affecte pas app()->environment() — il faut
        // détecter l'environnement pour que la gate prod s'applique.
        app()->detectEnvironment(fn () => 'production');

        $this->get('/docs')->assertForbidden();
        $this->get('/docs/openapi.yaml')->assertForbidden();
        $this->get('/tester-guide')->assertForbidden();
        $this->get('/api-explorer')->assertForbidden();
    }

    public function test_authenticated_tenant_user_can_access_docs_in_production(): void
    {
        // #5588 : config()->set n'affecte pas app()->environment() — il faut
        // détecter l'environnement pour que la gate prod s'applique.
        app()->detectEnvironment(fn () => 'production');

        // #5697 : company_id n'est PAS fillable sur Employee (Core\Auth) — un
        // mass-assignment via le constructeur l'ignore silencieusement et la
        // Gate viewApiDocs ($user && $user->company_id) renvoie 403 en prod.
        // Assignation directe = reflète un user hydraté depuis la DB (Sanctum).
        $employee = new Employee;
        $employee->company_id = '00000000-0000-0000-0000-000000000001';

        $this->actingAs($employee, 'web')
            ->get('/docs')
            ->assertOk();

        $this->actingAs($employee, 'web')
            ->get('/docs/openapi.yaml')
            ->assertOk();
    }

    public function test_docs_remain_public_outside_production(): void
    {
        // Environnement de test par défaut : ouvert (comportement historique).
        $this->get('/docs')->assertOk();
    }

    protected function tearDown(): void
    {
        // Restaurer l'environnement de test pour les classes suivantes.
        app()->detectEnvironment(fn () => 'testing');
        parent::tearDown();
    }
}
