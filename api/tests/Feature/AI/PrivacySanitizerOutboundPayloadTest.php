<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\LLMClient;
use App\AI\Providers\ClaudeClient;
use App\AI\Providers\GroqClient;
use App\AI\Providers\OpenAIClient;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-001 (#8141) — minimisation RGPD VÉRIFIÉE À LA FRONTIÈRE HTTP.
 *
 * Preuve exigée par l'issue : « une fixture contenant email/téléphone/ID
 * national dans un résultat de tool → 0 occurrence dans le payload sortant »,
 * pour les 3 drivers cloud (Claude / OpenAI / Groq), y compris la branche
 * Claude où `tool_result` est un TABLEAU de blocs (et non une string).
 *
 * Le test joue la vraie boucle tool-calling de l'Orchestrator : 1er appel LLM
 * scripté → tool call `get_employees` → le vrai handler d'IntentEngine renvoie
 * un employé porteur d'une PII → 2e appel LLM intercepté et enregistré.
 * L'assertion porte sur le corps HTTP réellement envoyé au provider, pas sur
 * l'état interne du sanitizer.
 */
class PrivacySanitizerOutboundPayloadTest extends TestCase
{
    use CreatesMvpSchema;

    private const PII_EMAIL = 'jean.dupont@exemple.fr';

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

    public function test_claude_payload_has_no_pii_in_tool_result_arrays(): void
    {
        $this->assertNoPiiInOutboundPayload($this->runToolLoop('claude'), 'claude');
    }

    public function test_openai_payload_has_no_pii_in_tool_result(): void
    {
        $this->assertNoPiiInOutboundPayload($this->runToolLoop('openai'), 'openai');
    }

    public function test_groq_payload_has_no_pii_in_tool_result(): void
    {
        $this->assertNoPiiInOutboundPayload($this->runToolLoop('groq'), 'groq');
    }

    /**
     * Joue la boucle complète (tool call → vrai handler → second appel LLM) et
     * retourne les corps HTTP sortants réellement enregistrés.
     *
     * @return list<array<string, mixed>>
     */
    private function runToolLoop(string $driver): array
    {
        $company = Company::factory()->create();
        $company->features = ['ai_cloud_allowed' => true];
        $company->save();

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'email' => self::PII_EMAIL,
        ]);

        Sanctum::actingAs($manager);

        // Les clés/config AVANT l'instanciation du client : les providers lisent
        // `config()` dans leur constructeur (un client construit avant la config
        // garderait une clé vide — Groq refuse alors d'appeler, sans réseau).
        config([
            'ai.providers.claude.key' => 'test-claude-key',
            'ai.providers.openai.key' => 'test-openai-key',
            'ai.providers.groq.key' => 'test-groq-key',
        ]);

        $this->app->instance(LLMClient::class, match ($driver) {
            'claude' => new ClaudeClient,
            'groq' => new GroqClient,
            default => new OpenAIClient,
        });

        // 1er appel = tool call scripté, 2e appel = texte final (fin de boucle).
        // On fake les 3 hôtes pour que le test reste valable quel que soit le
        // provider actif ; seuls les appels réellement émis sont enregistrés.
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->toolCallPayload('claude'), 200)
                ->push($this->finalTextPayload('claude'), 200),
            'api.openai.com/*' => Http::sequence()
                ->push($this->toolCallPayload('openai'), 200)
                ->push($this->finalTextPayload('openai'), 200),
            'api.groq.com/*' => Http::sequence()
                ->push($this->toolCallPayload('groq'), 200)
                ->push($this->finalTextPayload('groq'), 200),
        ]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Liste les employés de mon équipe.'])
            ->assertOk()
            ->assertJsonPath('data.tools_used.0', 'get_employees');

        $bodies = [];
        /** @var array{0: Request, 1: \Illuminate\Http\Client\Response} $pair */
        foreach (Http::recorded() as $pair) {
            $bodies[] = $this->requestBody($pair[0]);
        }

        $this->assertCount(2, $bodies, "2 appels LLM attendus pour {$driver}");

        return $bodies;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request->data();

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $bodies
     */
    private function assertNoPiiInOutboundPayload(array $bodies, string $driver): void
    {
        $outbound = json_encode($bodies, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString(self::PII_EMAIL, $outbound, "PII en clair dans le payload {$driver}");
        $this->assertStringContainsString('[email]', $outbound, "PII non masquée (marqueur [email] absent) pour {$driver}");
    }

    /**
     * @return array<string, mixed>
     */
    private function toolCallPayload(string $driver): array
    {
        if ($driver === 'claude') {
            return [
                'content' => [[
                    'type' => 'tool_use',
                    'id' => 'toolu_bos001',
                    'name' => 'get_employees',
                    'input' => ['limit' => 5],
                ]],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 4],
            ];
        }

        return [
            'choices' => [[
                'message' => [
                    'content' => '',
                    'tool_calls' => [[
                        'id' => 'call_bos001',
                        'type' => 'function',
                        'function' => ['name' => 'get_employees', 'arguments' => '{"limit":5}'],
                    ]],
                ],
            ]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finalTextPayload(string $driver): array
    {
        if ($driver === 'claude') {
            return [
                'content' => [['type' => 'text', 'text' => 'Voici la liste.']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 4],
            ];
        }

        return [
            'choices' => [['message' => ['content' => 'Voici la liste.']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4],
        ];
    }
}
