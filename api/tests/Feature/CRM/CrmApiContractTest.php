<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrats API CRM client — Issue #5712 (CRM-V0-08).
 *
 * Verrouille l'alignement routes ↔ OpenAPI ↔ implémentation :
 *   1. toutes les routes `/api/v1/crm/*` sont déclarées (router) et couvertes
 *      par une opération OpenAPI (paths + méthodes + noms de paramètres) ;
 *   2. l'espace CRM client est distinct du CRM Platform (aucune route
 *      `/platform/crm*` ajoutée, prefixe `/crm` dédié) ;
 *   3. la réponse de liste expose le contrat paginé Laravel (data/meta) ;
 *   4. l'authentification est requise (401 sans token) ;
 *   5. le SDK généré est synchronisé avec la spec (garde `--check`).
 */
class CrmApiContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private const CRM_PATHS = [
        'GET /crm/leads',
        'POST /crm/leads',
        'GET /crm/leads/{lead}',
        'PUT /crm/leads/{lead}',
        'DELETE /crm/leads/{lead}',
        'GET /crm/opportunities',
        'POST /crm/opportunities',
        'GET /crm/opportunities/{opportunity}',
        'PUT /crm/opportunities/{opportunity}',
        'DELETE /crm/opportunities/{opportunity}',
        'GET /crm/pipelines',
        'POST /crm/pipelines',
        'GET /crm/pipelines/{pipeline}',
        'PUT /crm/pipelines/{pipeline}',
        'DELETE /crm/pipelines/{pipeline}',
        'POST /crm/pipelines/{pipeline}/stages',
        'PUT /crm/pipelines/{pipeline}/stages/{stage}',
        'DELETE /crm/pipelines/{pipeline}/stages/{stage}',
        'GET /crm/tasks',
        'POST /crm/tasks',
        'GET /crm/tasks/{task}',
        'PUT /crm/tasks/{task}',
        'DELETE /crm/tasks/{task}',
        'POST /crm/tasks/{task}/assignees/{employee}',
        'DELETE /crm/tasks/{task}/assignees/{employee}',
        'GET /crm/activities',
        'POST /crm/activities',
        'DELETE /crm/activities/{activity}',
    ];

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    public function test_all_crm_routes_are_registered(): void
    {
        $registered = [];
        foreach (Route::getRoutes() as $route) {
            $uri = (string) $route->uri();
            if (str_starts_with($uri, 'api/v1/crm')) {
                $registered[] = strtoupper(implode('|', $route->methods())).' /'.str_replace('api/v1/', '', $uri);
            }
        }

        foreach (self::CRM_PATHS as $path) {
            $this->assertContains($path, $registered, "route manquante : {$path}");
        }
    }

    public function test_each_crm_route_is_documented_in_openapi_with_matching_parameters(): void
    {
        $spec = $this->readOpenApi();

        foreach (self::CRM_PATHS as $contract) {
            [$method, $path] = explode(' ', $contract, 2);

            $this->assertArrayHasKey($path, $spec['paths'], "chemin OpenAPI manquant : {$path}");
            $this->assertContains(
                strtolower($method),
                $spec['paths'][$path]['methods'],
                "opération OpenAPI manquante : {$method} {$path}"
            );

            // Noms de paramètres de chemin alignés (garde #5583).
            preg_match_all('/\{([^}]+)\}/', $path, $matches);
            foreach ($matches[1] as $param) {
                $this->assertContains(
                    $param,
                    $spec['paths'][$path]['parameters'],
                    "paramètre de chemin {$param} non documenté sur {$path}"
                );
            }
        }
    }

    public function test_crm_client_surface_is_distinct_from_platform_crm(): void
    {
        $spec = $this->readOpenApi();

        foreach (array_keys($spec['paths']) as $path) {
            $this->assertStringNotContainsString('/platform/crm', $path, 'le CRM client ne doit pas s\'exposer sous /platform');
        }

        $this->assertNotEmpty(array_filter(array_keys($spec['paths']), static fn (string $p): bool => str_starts_with($p, '/crm/')));
    }

    public function test_openapi_documents_crm_schemas_and_tag(): void
    {
        $spec = $this->readOpenApi();

        $expectedSchemas = [
            'CrmLead',
            'CrmLeadPayload',
            'CrmOpportunity',
            'CrmOpportunityPayload',
            'CrmPipeline',
            'CrmPipelinePayload',
            'CrmPipelineStage',
            'CrmPipelineStagePayload',
            'CrmTask',
            'CrmTaskPayload',
            'CrmActivity',
            'CrmActivityPayload',
            'CrmPaginationMeta',
        ];
        foreach ($expectedSchemas as $schema) {
            $this->assertContains($schema, $spec['schemas'], "schéma OpenAPI manquant : {$schema}");
        }

        $this->assertContains('CRM', $spec['tags']);
    }

    public function test_list_response_uses_laravel_paginated_contract(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/crm/leads');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_crm_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/crm/leads')->assertStatus(401);
        $this->getJson('/api/v1/crm/pipelines')->assertStatus(401);
        $this->getJson('/api/v1/crm/tasks')->assertStatus(401);
    }

    public function test_sdk_is_synchronized_with_spec(): void
    {
        $generator = base_path('../dev-hub/tools/generate-openapi-sdk.mjs');

        if (! is_file($generator)) {
            $this->markTestSkipped('générateur SDK indisponible dans cet environnement de test');
        }

        exec('node '.escapeshellarg($generator).' --check 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'SDK désynchronisé de api/openapi.yaml : '.implode("\n", $output));
    }

    /**
     * Parse api/openapi.yaml sans dépendance externe (symfony/yaml n'est pas
     * une dépendance directe du dépôt) : extrait les chemins, méthodes,
     * paramètres de chemin, schémas et tags nécessaires au contrat CRM.
     *
     * @return array{paths: array<string, array{methods: list<string>, parameters: list<string>}>, schemas: list<string>, tags: list<string>}
     */
    private function readOpenApi(): array
    {
        $path = base_path('openapi.yaml');

        $this->assertFileExists($path);

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines);

        $paths = [];
        $schemas = [];
        $tags = [];

        $section = null;
        $currentPath = null;
        $currentMethod = null;
        $inParameters = false;

        foreach ($lines as $line) {
            if ($line === 'tags:' && $section === null) {
                $section = 'tags';

                continue;
            }
            if ($line === 'paths:') {
                $section = 'paths';

                continue;
            }
            if ($line === 'components:') {
                $section = 'components';

                continue;
            }
            if ($section === 'tags' && str_starts_with($line, '- name: ')) {
                $tags[] = trim(substr($line, strlen('- name: ')));
            }
            if ($section === 'components' && str_starts_with($line, '  schemas:')) {
                $section = 'schemas';
            }
            if ($section === 'schemas' && preg_match('/^    ([A-Za-z0-9_]+):$/', $line, $m) === 1) {
                $schemas[] = $m[1];
            }
            if ($section === 'paths') {
                if (preg_match('/^  (\/[^ ]+):$/', $line, $m) === 1) {
                    $currentPath = $m[1];
                    $paths[$currentPath] = ['methods' => [], 'parameters' => []];
                    $currentMethod = null;
                    $inParameters = false;

                    continue;
                }
                if ($currentPath !== null && preg_match('/^    ([a-z]+):$/', $line, $m) === 1 && in_array($m[1], ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $currentMethod = $m[1];
                    $paths[$currentPath]['methods'][] = $currentMethod;
                    $inParameters = false;

                    continue;
                }
                if ($currentPath !== null && $currentMethod !== null) {
                    if (trim($line) === 'parameters:') {
                        $inParameters = true;

                        continue;
                    }
                    if (trim($line) === 'responses:') {
                        $inParameters = false;
                    }
                    if ($inParameters && preg_match('/^      - name: ([A-Za-z0-9_]+)$/', $line, $m) === 1) {
                        $paths[$currentPath]['parameters'][] = $m[1];
                    }
                }
            }
        }

        return [
            'paths' => $paths,
            'schemas' => $schemas,
            'tags' => $tags,
        ];
    }
}
