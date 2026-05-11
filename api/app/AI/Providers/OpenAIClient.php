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
        /** @var string $key */
        $key = config('ai.providers.openai.key', '');
        $this->apiKey = $key;

        /** @var string $model */
        $model = config('ai.providers.openai.model', 'gpt-4o');
        $this->model = $model;

        /** @var string $baseUrl */
        $baseUrl = config('ai.providers.openai.base_url', 'https://api.openai.com/v1');
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $tools = []): AIResponse
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

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                return new AIResponse(
                    content: '',
                    error: 'OpenAI API error: '.$response->status(),
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
        return 'openai';
    }
}
