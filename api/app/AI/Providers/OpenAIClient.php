<?php

namespace App\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use Illuminate\Support\Facades\Http;

class OpenAIClient implements LLMClient
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.openai.key') ?? '');

        $this->model = (string) (config('ai.providers.openai.model') ?? 'gpt-4o');

        $this->baseUrl = (string) (config('ai.providers.openai.base_url') ?: 'https://api.openai.com/v1');
    }

    public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse
    {
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

        // BOS-031 (#8221) — `response_format` optionnel, transmis tel quel
        // (OpenAI le supporte nativement : json_object, json_schema…).
        if ($responseFormat !== null) {
            $payload['response_format'] = $responseFormat;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                return new AIResponse(
                    content: '',
                    error: 'OpenAI API error: '.$response->status(),
                    status: $response->status(),
                    provider: $this->provider(),
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
                status: $response->status(),
                provider: $this->provider(),
            );
        } catch (\Throwable $e) {
            // 503 : panne de transport → échec réessayable (BOS-031).
            return new AIResponse(content: '', error: $e->getMessage(), status: 503, provider: $this->provider());
        }
    }

    public function provider(): string
    {
        return 'openai';
    }
}
