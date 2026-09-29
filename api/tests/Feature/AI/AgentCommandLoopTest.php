<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * C1 (#6859) — boucle agent « commande » v1 (EPIC #6846) : chat + tool-calling
 * natif borné (≤ 4 échanges LLM), réponses FR, exécution des outils du profil
 * avec arrêt propre. Les scénarios (a)-(d) du contrat sont joués avec un LLM
 * fake scripté (aucun appel externe) :
 *   (a) lecture → exécution directe → réponse finale ;
 *   (b) écriture sensible → confirmation requise (aucun effet de bord) ;
 *   (c) itérations max atteintes (le fake demande toujours un outil) → arrêt
 *       propre, réponse explicite, aucun outil exécuté au-delà de la borne ;
 *   (d) outil inconnu proposé par le fake → rejet (A4), la boucle continue et
 *       répond sans effet de bord.
 */
class AgentCommandLoopTest extends TestCase
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

    public function test_a_read_tool_is_executed_and_final_answer_returned(): void
    {
        // (a) lecture : l'outil est exécuté directement (read) et son résultat
        // est renvoyé au modèle qui produit la réponse finale.
        [$company, $manager] = $this->fixture();
        Sanctum::actingAs($manager);

        $client = $this->bindScriptedLlm([
            $this->toolResponse('call_1', 'get_headcount', []),
            $this->textResponse("L'effectif est disponible ci-dessus."),
        ]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Combien d employes actifs ?'])
            ->assertOk()
            ->assertJsonPath('data.response', "L'effectif est disponible ci-dessus.")
            ->assertJsonPath('data.tools_used.0', 'get_headcount')
            ->assertJsonPath('data.pending_confirmations', []);

        $this->assertSame(2, $client->calls, '1 appel outil + 1 appel final');
        $this->assertDatabaseCount('ai_conversations', 1);
    }

    public function test_b_sensitive_write_requires_confirmation_without_side_effect(): void
    {
        // (b) écriture sensible : le tool_call seul ne produit AUCUN effet de
        // bord — la boucle s'arrête et renvoie la confirmation à l'utilisateur.
        [$company, $manager] = $this->fixture();
        Sanctum::actingAs($manager);

        $client = $this->bindScriptedLlm([
            $this->toolResponse('call_1', 'absence_decision', [
                'absence_id' => 999,
                'decision' => 'approve',
            ]),
        ]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Approuve la demande 999'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->assertJsonPath('data.pending_confirmations.0.tool', 'absence_decision');

        $this->assertSame(1, $client->calls, 'la boucle s\'arrête dès la confirmation requise');
    }

    public function test_c_max_iterations_reached_stops_cleanly(): void
    {
        [$company, $manager] = $this->fixture();
        Sanctum::actingAs($manager);

        $client = $this->bindScriptedLlm([
            $this->toolResponse('call_1', 'get_headcount', []),
            $this->toolResponse('call_2', 'get_headcount', []),
            $this->toolResponse('call_3', 'get_headcount', []),
            $this->toolResponse('call_4', 'get_headcount', []),
        ]);

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Compte encore et encore'])->assertOk();
        $data = $res->json('data');

        $this->assertCount(3, $data['tools_used'] ?? [], '3 rounds d\'exécution max');
        $this->assertStringContainsString('limite d\'itérations', (string) ($data['response'] ?? ''));
        $this->assertSame(4, $client->calls);
    }

    public function test_d_unknown_tool_proposed_by_llm_is_rejected_and_loop_answers(): void
    {
        // (d) outil non déclaré (A4, fail-closed) : rejeté sans effet de bord,
        // le refus est renvoyé au modèle qui produit la réponse finale.
        [$company, $manager] = $this->fixture();
        Sanctum::actingAs($manager);

        $client = $this->bindScriptedLlm([
            $this->toolResponse('call_1', 'send_email_to_ceo', ['subject' => 'Bonjour']),
            $this->textResponse('Je ne peux pas envoyer de courriel.'),
        ]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Envoie un mail au CEO'])
            ->assertOk()
            ->assertJsonPath('data.response', 'Je ne peux pas envoyer de courriel.')
            ->assertJsonPath('data.tools_used.0', 'send_email_to_ceo')
            ->assertJsonPath('data.pending_confirmations', []);

        $this->assertSame(2, $client->calls);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fixture(): array
    {
        $company = Company::factory()->create();
        $this->assertInstanceOf(Company::class, $company);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->assertInstanceOf(Employee::class, $manager);

        return [$company, $manager];
    }

    /**
     * Binde le LLM fake scripté (file de réponses) et retourne l'instance
     * (type concret : accès $calls typé pour PHPStan level max).
     *
     * @param  array<int, AIResponse>  $queue
     */
    private function bindScriptedLlm(array $queue): ScriptedLlmClient
    {
        $client = new ScriptedLlmClient($queue);

        $this->app->instance(LLMClient::class, $client);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolResponse(string $callId, string $name, array $arguments): AIResponse
    {
        return new AIResponse(
            content: '',
            toolCalls: [new ToolCall($callId, $name, $arguments)],
            inputTokens: 5,
            outputTokens: 8,
            model: 'test-model',
        );
    }

    private function textResponse(string $content): AIResponse
    {
        return new AIResponse(
            content: $content,
            inputTokens: 5,
            outputTokens: 8,
            model: 'test-model',
        );
    }
}

/**
 * LLM fake scripté (file de réponses) pour les tests de la boucle C1 —
 * type concret (non anonyme) pour un accès typé au compteur d'appels.
 */
final class ScriptedLlmClient implements LLMClient
{
    public int $calls = 0;

    /** @var array<int, AIResponse> */
    private array $queue;

    /**
     * @param  array<int, AIResponse>  $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse
    {
        $this->calls++;

        if ($this->queue === []) {
            throw new \RuntimeException('Fake LLM script épuisé.');
        }

        return array_shift($this->queue);
    }

    public function provider(): string
    {
        return 'test';
    }
}
