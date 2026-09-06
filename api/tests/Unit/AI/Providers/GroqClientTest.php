<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\Providers\GroqClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #6848 — driver Groq : contrat LLMClient respecté, 0 appel réseau
 * dans les tests (Http::fake), erreurs actionnables.
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

    public function test_chat_success_returns_content_and_usage(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Bonjour'],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'salut']]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertFalse($response->failed());
        $this->assertSame('Bonjour', $response->content);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(5, $response->outputTokens);
        $this->assertSame('groq', (new GroqClient)->provider());
    }

    public function test_chat_parses_native_tool_calls(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'function' => [
                                'name' => 'absence_balance',
                                'arguments' => '{"employee_id":42}',
                            ],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
            ], 200),
        ]);

        $response = (new GroqClient)->chat(
            [['role' => 'user', 'content' => 'solde de Dupont']],
            [['type' => 'function', 'function' => ['name' => 'absence_balance']]],
        );

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('absence_balance', $response->toolCalls[0]->name);
        $this->assertSame(['employee_id' => 42], $response->toolCalls[0]->arguments);
    }

    public function test_missing_key_fails_actionable_without_network(): void
    {
        config()->set('ai.providers.groq.key', '');

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'x']]);

        $this->assertTrue($response->failed());
        $this->assertStringContainsString('GROQ_API_KEY', (string) $response->error);
        Http::assertNothingSent();
    }

    public function test_http_401_returns_actionable_error(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 401)]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'x']]);

        $this->assertTrue($response->failed());
        $this->assertStringContainsString('401', (string) $response->error);
    }

    public function test_http_429_returns_quota_error(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 429)]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'x']]);

        $this->assertTrue($response->failed());
        $this->assertStringContainsString('quota', (string) $response->error);
    }

    public function test_timeout_returns_error(): void
    {
        Http::fake(['api.groq.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);

        $response = (new GroqClient)->chat([['role' => 'user', 'content' => 'x']]);

        $this->assertTrue($response->failed());
        $this->assertStringContainsString('unreachable', (string) $response->error);
    }

    public function test_provider_name(): void
    {
        $this->assertSame('groq', (new GroqClient)->provider());
    }
}
