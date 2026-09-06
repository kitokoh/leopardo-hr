<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use Illuminate\Support\Facades\Http;

/**
 * Driver LLM Groq (free tier, API OpenAI-compatible — issue #6848).
 *
 * Même contrat que les providers existants ({@see LLMClient}) : appel
 * `POST https://api.groq.com/openai/v1/chat/completions`, tool-calling
 * natif, budget `max_tokens`/température depuis `config/ai.php`.
 *
 * Erreurs ACTIONNABLES (jamais de 500 muet) :
 * - clé absente → `AIResponse.error` explicite (« GROQ_API_KEY non
 *   configurée ») sans appel réseau ;
 * - timeout / HTTP ≥ 400 / JSON invalide → erreur portée par la réponse.
 *
 * Note : le parsing est volontairement aligné sur OpenAIClient (API
 * compatible) — la mutualisation en classe de base commune se fera si un
 * 3ᵉ driver OpenAI-compatible apparaît (pas d'abstraction prématurée).
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
        // Fail-fast actionnable : jamais d'appel réseau sans clé.
        if ($this->apiKey === '') {
            return new AIResponse(
                content: '',
                error: 'GROQ_API_KEY non configurée — renseigner config/ai.php (providers.groq.key) ou l’environnement (GROQ_API_KEY).',
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
                $message = match ($response->status()) {
                    401 => 'GROQ_API_KEY invalide ou révoquée (HTTP 401).',
                    429 => 'Groq : quota du free tier épuisé (HTTP 429) — réessayer plus tard.',
                    default => 'Groq API error: '.$response->status(),
                };

                return new AIResponse(content: '', error: $message);
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
                inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
                outputTokens: (int) ($usage['completion_tokens'] ?? 0),
                model: $this->model,
            );
        } catch (\Throwable $e) {
            return new AIResponse(content: '', error: 'Groq unreachable: '.$e->getMessage());
        }
    }

    public function provider(): string
    {
        return 'groq';
    }
}
