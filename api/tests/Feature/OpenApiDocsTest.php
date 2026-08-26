<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5588 (durcissement) : les routes docs (/docs, /tester-guide,
 * /api-explorer, /docs/openapi.yaml) sont désormais derrière la Gate
 * `viewApiDocs` — libres en local, sinon employé authentifié avec
 * company_id (AppServiceProvider). En test (APP_ENV=testing), sans
 * authentification → 403.
 */
class OpenApiDocsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_docs_routes_are_forbidden_without_authentication(): void
    {
        $this->get('/docs')->assertForbidden();
        $this->get('/tester-guide')->assertForbidden();
        $this->get('/api-explorer')->assertForbidden();
        $this->get('/docs/openapi.yaml')->assertForbidden();
    }

    public function test_swagger_ui_page_requires_authenticated_employee(): void
    {
        $this->actingAs($this->makeEmployee());

        $this->get('/docs')
            ->assertOk()
            ->assertSee('Leopardo RH API Docs')
            ->assertSee('/docs/openapi.yaml');
    }

    public function test_openapi_yaml_is_served_from_the_canonical_spec(): void
    {
        $this->actingAs($this->makeEmployee());

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

        $this->actingAs($this->makeEmployee());

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
        $this->actingAs($this->makeEmployee());

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

    private function makeEmployee(): Employee
    {
        $company = Company::factory()->create();

        return Employee::query()->forceCreate([
            'company_id' => $company->id,
            'first_name' => 'Docs',
            'last_name' => 'Employee',
            'email' => 'docs-employee@example.test',
            'matricule' => 'DOCS-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
    }
}
