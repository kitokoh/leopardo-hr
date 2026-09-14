<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\AI\IntentEngine;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7356 — garde d'EXÉCUTION des outils lecture historiques de l'annuaire
 * (`get_employees`, `get_employee_details`, `search_employees`).
 *
 * Ces trois outils n'avaient aucun test d'exécution : la matrice de
 * permissions (`ToolPermissionMatrixTest`) vérifie *qui* peut appeler l'outil,
 * jamais que le handler s'exécute réellement contre le schéma. Ils
 * sélectionnaient une colonne `post` inexistante sur `employees` (le schéma
 * porte `position_id`) et `hire_date` (le schéma porte `contract_start`) :
 * `SQLSTATE[42703]` en production, invisible en CI.
 *
 * Ce test exécute les trois outils via `IntentEngine` (le chemin réellement
 * emprunté par l'orchestrateur) et échoue si un handler lève ou renvoie
 * `error`.
 */
class LegacyReadToolsExecutionTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function executeTool(string $companyId, int $userId, string $name, array $arguments = []): ToolResult
    {
        $engine = app(IntentEngine::class);

        return $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', $name, $arguments)]),
            $companyId,
            $userId,
        )[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolResult $result): array
    {
        $decoded = json_decode($result->content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function seedTeam(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    public function test_get_employees_executes_against_the_real_schema(): void
    {
        [$company, $manager] = $this->seedTeam();

        $result = $this->executeTool((string) $company->id, $manager->id, 'get_employees');

        $this->assertTrue($result->success, 'get_employees ne doit pas échouer: '.$result->content);
        $data = $this->payload($result);
        $this->assertArrayNotHasKey('error', $data, 'get_employees ne doit pas renvoyer error: '.$result->content);
        $this->assertSame(2, $data['count'] ?? null);
        $this->assertNotEmpty($data['employees'] ?? []);
    }

    public function test_get_employee_details_executes_against_the_real_schema(): void
    {
        [$company, $manager, $employee] = $this->seedTeam();

        $result = $this->executeTool(
            (string) $company->id,
            $manager->id,
            'get_employee_details',
            ['employee_id' => $employee->id],
        );

        $this->assertTrue($result->success, 'get_employee_details ne doit pas échouer: '.$result->content);
        $data = $this->payload($result);
        $this->assertArrayNotHasKey('error', $data, 'get_employee_details ne doit pas renvoyer error: '.$result->content);
        $this->assertSame('Amina', $data['employee']['first_name'] ?? null);
    }

    public function test_search_employees_executes_against_the_real_schema(): void
    {
        [$company, $manager] = $this->seedTeam();

        $result = $this->executeTool(
            (string) $company->id,
            $manager->id,
            'search_employees',
            ['query' => 'Zerrouki'],
        );

        $this->assertTrue($result->success, 'search_employees ne doit pas échouer: '.$result->content);
        $data = $this->payload($result);
        $this->assertArrayNotHasKey('error', $data, 'search_employees ne doit pas renvoyer error: '.$result->content);
        $this->assertSame(1, $data['count'] ?? null);
    }

    /**
     * La recherche doit couvrir l'intitulé de poste (l'intention d'origine de
     * la colonne `post`) sans dépendre d'une colonne inexistante.
     */
    public function test_search_employees_matches_job_title(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'extra_data' => ['job_title' => 'Technicienne reseau'],
        ]);

        $result = $this->executeTool(
            (string) $company->id,
            $manager->id,
            'search_employees',
            ['query' => 'Technicienne'],
        );

        $this->assertTrue($result->success);
        $data = $this->payload($result);
        $this->assertArrayNotHasKey('error', $data, $result->content);
        $this->assertSame(1, $data['count'] ?? null, 'la recherche doit matcher le job_title: '.$result->content);
    }
}
