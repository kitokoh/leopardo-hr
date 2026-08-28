<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Contrats OpenAPI du CRM client — Issue #5712 (CRM-V0-08).
 *
 * Verrouille que le contrat publié (`api/openapi.yaml`) documente bien
 * l'implémentation `/api/v1/crm/*` :
 *   1. les 16 chemins CRM (CRUD leads/opportunités/activités/tâches/
 *      comptes/contacts/pipelines) sont présents ;
 *   2. les schémas associés existent (CrmLead, CrmOpportunity, CrmActivity,
 *      CrmTask, CrmAccount, CrmContact, CrmPipeline, CrmPipelineStage) ;
 *   3. les routes CRM client sont DISTINCTES des routes Platform CRM
 *      (aucun doublon `/platform/crm/...`) ;
 *   4. chaque chemin CRM déclare les réponses attendues (403/404/422).
 *
 * La couverture route→spécification complète est gardée par
 * `check-openapi-route-coverage.py` (CI OpenAPI) — ce test verrouille le
 * contenu sémantique du contrat CRM.
 */
class OpenApiCrmContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path('openapi.yaml');
        $this->assertFileExists($path, 'api/openapi.yaml manquant');

        $parsed = Yaml::parse((string) File::get($path));

        $this->assertIsArray($parsed);

        /** @var array<string, mixed> $parsed */
        $this->spec = $parsed;
    }

    /** @return list<string> */
    private function crmPaths(): array
    {
        $paths = $this->spec['paths'] ?? [];

        $crm = array_values(array_filter(
            array_keys($paths),
            static fn (string $p): bool => str_starts_with($p, '/crm/')
        ));

        sort($crm);

        return $crm;
    }

    public function test_all_crm_routes_are_documented(): void
    {
        $expected = [
            '/crm/leads',
            '/crm/leads/{lead}',
            '/crm/opportunities',
            '/crm/opportunities/{opportunity}',
            '/crm/activities',
            '/crm/activities/{activity}',
            '/crm/tasks',
            '/crm/tasks/{task}',
            '/crm/accounts',
            '/crm/accounts/{account}',
            '/crm/contacts',
            '/crm/contacts/{contact}',
            '/crm/pipelines',
            '/crm/pipelines/{pipeline}',
            '/crm/pipelines/{pipeline}/stages',
            '/crm/pipelines/{pipeline}/stages/{stage}',
        ];

        sort($expected);

        $this->assertSame($expected, $this->crmPaths(), 'Tous les chemins CRM de l\'implémentation doivent être documentés');
    }

    public function test_crm_schemas_exist(): void
    {
        $schemas = array_keys($this->spec['components']['schemas'] ?? []);

        foreach ([
            'CrmLead',
            'CrmLeadPayload',
            'CrmOpportunity',
            'CrmOpportunityPayload',
            'CrmActivity',
            'CrmActivityPayload',
            'CrmTask',
            'CrmTaskPayload',
            'CrmAccount',
            'CrmAccountPayload',
            'CrmContact',
            'CrmContactPayload',
            'CrmPipeline',
            'CrmPipelinePayload',
            'CrmPipelineStage',
        ] as $schema) {
            $this->assertContains($schema, $schemas, "Schéma OpenAPI manquant : {$schema}");
        }
    }

    public function test_crm_paths_are_distinct_from_platform_crm(): void
    {
        $paths = array_keys($this->spec['paths'] ?? []);

        foreach ($this->crmPaths() as $crmPath) {
            $this->assertNotContains(
                '/platform'.$crmPath,
                $paths,
                "La route Platform CRM {$crmPath} ne doit pas exister : le CRM client est distinct ({$crmPath})."
            );
        }
    }

    public function test_crm_mutations_declare_expected_errors(): void
    {
        $paths = $this->spec['paths'] ?? [];

        foreach (['/crm/leads', '/crm/opportunities', '/crm/tasks', '/crm/accounts', '/crm/contacts'] as $collection) {
            $post = $paths[$collection]['post'] ?? null;

            $this->assertNotNull($post, "POST {$collection} doit être documenté");

            $responses = $post['responses'] ?? [];

            foreach (['403', '422'] as $status) {
                $this->assertArrayHasKey($status, $responses, "POST {$collection} doit déclarer {$status}");
            }
        }

        foreach (['/crm/leads/{lead}', '/crm/opportunities/{opportunity}', '/crm/tasks/{task}'] as $item) {
            $get = $paths[$item]['get'] ?? null;

            $this->assertNotNull($get, "GET {$item} doit être documenté");

            $responses = $get['responses'] ?? [];

            foreach (['403', '404'] as $status) {
                $this->assertArrayHasKey($status, $responses, "GET {$item} doit déclarer {$status}");
            }
        }
    }
}
