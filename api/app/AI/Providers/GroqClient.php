<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use Illuminate\Support\Facades\Http;

/**
 * Client LLM Groq — issue #6848 (BC-23, EPIC #6846).
 *
 * Groq expose une API compatible OpenAI (https://api.groq.com/openai/v1) :
 * la structure de payload/réponse (chat completions, tool_calls, usage) est
 * identique à OpenAIClient. Free tier : aucun coût obligatoire pour le
 * périmètre v1 de l'assistant (commandes texte & voix des managers).
 *
 * Sélection via `AI_PROVIDER=groq` (config/ai.php, binding AppServiceProvider).
 * Fail-fast : si la clé est absente, aucune requête HTTP n'est émise et une
 * erreur actionnable est retournée (jamais de 500 muet).
 */
class GroqClient implements LLMClient
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.groq.key') ?? '');

        $this->model = (string) (config('ai.providers.groq.model') ?? 'llama-3.3-70b-versatile');

        $this->baseUrl = (string) (config('ai.providers.groq.base_url') ?? 'https://api.groq.com/openai/v1');
    }

    public function chat(array $messages, array $tools = []): AIResponse
    {
        if ($this->apiKey === '') {
            return new AIResponse(
                content: '',
                error: 'Groq API key missing — set GROQ_API_KEY (config ai.providers.groq.key).',
            );
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => (int) config('ai.max_tokens', 1024),
            'temperature' => (float) config('ai.temperature', 0.3),
        ];

        if (count($tools) > 0) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                return new AIResponse(
                    content: '',
                    error: 'Groq API error: '.$response->status(),
                );
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? [];
            $message = $choice['message'] ?? [];
            $usage = $data['usage'] ?? [];

            $toolCalls = [];
            foreach ($message['tool_calls'] ?? [] as $tc) {
                /** @var string $args */
                $args = $tc['function']['arguments'] ?? '{}';
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($args, true) ?: [];
                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? '',
                    name: $tc['function']['name'] ?? '',
                    arguments: $decoded,
                );
            }

            return new AIResponse(
                content: $message['content'] ?? '',
                toolCalls: $toolCalls,
                inputTokens: $usage['prompt_tokens'] ?? 0,
                outputTokens: $usage['completion_tokens'] ?? 0,
                model: $this->model,
            );
        } catch (\Throwable $e) {
            return new AIResponse(content: '', error: $e->getMessage());
        }
    }

    public function provider(): string
    {
        return 'groq';
    }
}
