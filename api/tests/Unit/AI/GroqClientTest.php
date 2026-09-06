<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Providers\GroqClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #6848 (BC-23) — GroqClient : driver LLM Groq (API OpenAI-compatible).
 *
 * - succès (réponse simple et tool_calls) ;
 * - erreur HTTP → AIResponse.error actionnable ;
 * - clé absente → aucune requête HTTP, erreur explicite (pas de 500 muet) ;
 * - exception transport → AIResponse.error.
 */
class GroqClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.providers.groq.key', 'test-groq-key');
        config()->set('ai.providers.groq.model', 'llama-3.3-70b-versatile');
        config()->set('ai.providers.groq.base_url', 'https://api.groq.com/openai/v1');
    }

    public function test_chat_returns_content_and_usage(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Bonjour, je peux vous aider.']],
                ],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
            ], 200),
        ]);

        $client = new GroqClient;
        $response = $client->chat([['role' => 'user', 'content' => 'Bonjour']]);

        self::assertNull($response->error);
        self::assertSame('Bonjour, je peux vous aider.', $response->content);
        self::assertSame(12, $response->inputTokens);
        self::assertSame(8, $response->outputTokens);
        self::assertSame('groq', $client->provider());

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['model'] === 'llama-3.3-70b-versatile'
                && $request->hasHeader('Authorization', 'Bearer test-groq-key');
        });
    }

    public function test_chat_parses_tool_calls(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_1',
                                    'function' => [
                                        'name' => 'team_absences_recent',
                                        'arguments' => '{"period":"week"}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
            ], 200),
        ]);

        $response = (new GroqClient)->chat(
            [['role' => 'user', 'content' => 'Absences cette semaine ?']],
            [['type' => 'function', 'function' => ['name' => 'team_absences_recent']]],
        );

        self::assertNull($response->error);
        self::assertTrue($response->hasToolCalls());
        self::assertSame('team_absences_recent', $response->toolCalls[0]->name);
        self::assertSame(['period' => 'week'], $response->toolCalls[0]->arguments);
    }

    public function test_chat_returns_actionable_error_on_http_failure(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'invalid api key'], 401),
        ]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'test']]);

        self::assertSame('', $response->content);
        self::assertStringContainsString('Groq API error: 401', (string) $response->error);
    }

    public function test_chat_without_api_key_does_not_call_http(): void
    {
        config()->set('ai.providers.groq.key', '');

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'test']]);

        self::assertStringContainsString('GROQ_API_KEY', (string) $response->error);
        Http::assertNothingSent();
    }

    public function test_chat_catches_transport_exception(): void
    {
        Http::fake([
            'api.groq.com/*' => fn () => throw new \RuntimeException('connection refused'),
        ]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'test']]);

        self::assertStringContainsString('connection refused', (string) $response->error);
    }
}
