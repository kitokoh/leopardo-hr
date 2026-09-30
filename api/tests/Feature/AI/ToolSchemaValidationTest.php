<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\IntentEngine;
use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use App\AI\Support\AIToolSensitivity;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Facades\Log;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-034 (#8223) — validation RUNTIME des schémas de tools.
 *
 * Avant ce lot : `outputSchema` était déclaré dans le contrat des tools
 * (A3 #6850) mais JAMAIS validé à l'exécution, et les arguments produits
 * par le LLM n'étaient pas validés par schéma avant dispatch — un argument
 * hostile ou malformé atteignait les handlers (casts défensifs uniquement).
 *
 * Ces tests verrouillent le critère #2 de l'issue et la bascule de mode
 * (critère #3) :
 *  - mode `strict` : toute violation (argument hors enum, type erroné,
 *    clé requise absente, sortie non conforme à l'outputSchema déclaré) =
 *    refus fail-closed + audit, AVANT tout effet de bord ;
 *  - mode `warn` (défaut, 1 semaine) : la violation est auditée (log
 *    structuré `ai.tool_schema_violation`) et laissée passer ;
 *  - un outil legacy sans schéma déclaré n'est JAMAIS bloqué (fail-open
 *    sur l'absence de schéma).
 */
class ToolSchemaValidationTest extends TestCase
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
        AIToolDefinitionRegistry::reset();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);

        return [$company, $principal];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $tool, array $arguments, Company $company, Employee $actor): array
    {
        $engine = app(IntentEngine::class);

        $results = $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', $tool, $arguments)]),
            (string) $company->id,
            (int) $actor->id,
        );

        return json_decode($results[0]->content, true) ?: [];
    }

    public function test_strict_mode_rejects_an_argument_outside_the_declared_enum(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);
        Log::spy();

        $payload = $this->callTool('absence_decision', [
            'absence_id' => 12,
            'decision' => 'maybe',
        ], $company, $principal);

        $this->assertSame('AI_TOOL_INPUT_SCHEMA_VIOLATION', $payload['error'] ?? null);
        // Aucune proposition de confirmation ne doit être créée (fail-closed
        // AVANT tout effet de bord).
        $this->assertNull($payload['pending_action_id'] ?? null);

        // @phpstan-ignore-next-line staticMethod.notFound (Log::spy → MockInterface dynamique)
        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => $message === 'ai.tool_schema_violation'
                && ($context['tool'] ?? null) === 'absence_decision'
                && ($context['phase'] ?? null) === 'input'
                && ($context['mode'] ?? null) === 'strict',
        )->once();
    }

    public function test_strict_mode_rejects_a_missing_required_argument(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);

        $payload = $this->callTool('absence_decision', [
            'absence_id' => 12,
        ], $company, $principal);

        $this->assertSame('AI_TOOL_INPUT_SCHEMA_VIOLATION', $payload['error'] ?? null);
        $violations = $payload['violations'] ?? [];
        $this->assertIsArray($violations);
        $this->assertStringContainsString('decision', implode(' | ', array_map('strval', $violations)));
    }

    public function test_strict_mode_rejects_a_wrongly_typed_argument(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);

        $payload = $this->callTool('absence_decision', [
            'absence_id' => 'abc',
            'decision' => 'approve',
        ], $company, $principal);

        $this->assertSame('AI_TOOL_INPUT_SCHEMA_VIOLATION', $payload['error'] ?? null);
    }

    public function test_warn_mode_audits_but_lets_the_call_through(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'warn']);
        Log::spy();

        $payload = $this->callTool('absence_decision', [
            'absence_id' => 12,
            'decision' => 'maybe',
        ], $company, $principal);

        // Mode warn : la proposition de confirmation suit son cours normal.
        $this->assertSame('confirmation_required', $payload['status'] ?? null);

        // @phpstan-ignore-next-line staticMethod.notFound (Log::spy → MockInterface dynamique)
        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => $message === 'ai.tool_schema_violation'
                && ($context['phase'] ?? null) === 'input'
                && ($context['mode'] ?? null) === 'warn',
        )->once();
    }

    public function test_valid_arguments_pass_in_strict_mode(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);

        $payload = $this->callTool('absence_decision', [
            'absence_id' => 12,
            'decision' => 'approve',
        ], $company, $principal);

        $this->assertSame('confirmation_required', $payload['status'] ?? null);
    }

    public function test_strict_mode_rejects_an_output_violating_the_declared_output_schema(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);
        Log::spy();

        // Définition de TEST pour un outil legacy (aucune définition boot) :
        // outputSchema volontairement inatteignable (clé requise absente de
        // la sortie réelle) pour éprouver le refus de sortie.
        if (! AIToolDefinitionRegistry::has('get_absences')) {
            AIToolDefinitionRegistry::register(new AIToolDefinition(
                name: 'get_absences',
                description: 'Définition de test BOS-034 (outputSchema inatteignable).',
                inputSchema: ['type' => 'object', 'properties' => new \stdClass],
                outputSchema: [
                    'type' => 'object',
                    'required' => ['__never_present__'],
                    'properties' => ['__never_present__' => ['type' => 'string']],
                ],
                permission: 'absences.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-06',
            ));
            $this->app->forgetInstance(ToolRegistry::class);
        }

        $payload = $this->callTool('get_absences', [], $company, $principal);

        $this->assertSame('AI_TOOL_OUTPUT_SCHEMA_VIOLATION', $payload['error'] ?? null);

        // @phpstan-ignore-next-line staticMethod.notFound (Log::spy → MockInterface dynamique)
        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => $message === 'ai.tool_schema_violation'
                && ($context['phase'] ?? null) === 'output',
        )->once();
    }

    public function test_warn_mode_lets_a_non_conforming_output_through_with_an_audit_trail(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'warn']);
        Log::spy();

        if (! AIToolDefinitionRegistry::has('get_absences')) {
            AIToolDefinitionRegistry::register(new AIToolDefinition(
                name: 'get_absences',
                description: 'Définition de test BOS-034 (outputSchema inatteignable).',
                inputSchema: ['type' => 'object', 'properties' => new \stdClass],
                outputSchema: [
                    'type' => 'object',
                    'required' => ['__never_present__'],
                    'properties' => ['__never_present__' => ['type' => 'string']],
                ],
                permission: 'absences.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-06',
            ));
            $this->app->forgetInstance(ToolRegistry::class);
        }

        $payload = $this->callTool('get_absences', [], $company, $principal);

        $this->assertNull($payload['error'] ?? null, 'mode warn : la sortie non conforme passe');

        // @phpstan-ignore-next-line staticMethod.notFound (Log::spy → MockInterface dynamique)
        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => $message === 'ai.tool_schema_violation'
                && ($context['phase'] ?? null) === 'output'
                && ($context['mode'] ?? null) === 'warn',
        )->once();
    }

    public function test_a_tool_without_declared_schema_is_never_blocked(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.tool_schema_validation.mode' => 'strict']);

        // `get_headcount` : outil legacy sans AIToolDefinition enregistrée —
        // seul le schéma `parameters` du registre (permissif) s'applique à
        // l'entrée, et AUCUNE validation de sortie (fail-open documenté).
        $payload = $this->callTool('get_headcount', [], $company, $principal);

        $this->assertNull($payload['error'] ?? null);
    }
}
