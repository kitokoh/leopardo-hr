<?php

namespace App\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use Illuminate\Support\Facades\Http;

class ClaudeClient implements LLMClient
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        /** @var string $key */
        $key = config('ai.providers.claude.key', '');
        $this->apiKey = $key;

        /** @var string $model */
        $model = config('ai.providers.claude.model', 'claude-sonnet-4-20250514');
        $this->model = $model;

        /** @var string $baseUrl */
        $baseUrl = config('ai.providers.claude.base_url', 'https://api.anthropic.com/v1');
        $this->baseUrl = $baseUrl;
    }

    public function chat(array $messages, array $tools = []): AIResponse
    {
        $systemMessage = '';
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage .= $msg['content']."\n";
            } else {
                $filteredMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => (int) config('ai.max_tokens', 1024),
            'messages' => $filteredMessages,
        ];

        if ($systemMessage !== '') {
            $payload['system'] = trim($systemMessage);
        }

        if (count($tools) > 0) {
            $claudeTools = array_map(function (array $tool): array {
                $fn = $tool['function'] ?? $tool;

                return [
                    'name' => $fn['name'] ?? '',
                    'description' => $fn['description'] ?? '',
                    'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass],
                ];
            }, $tools);
            $payload['tools'] = $claudeTools;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post("{$this->baseUrl}/messages", $payload);

            if ($response->failed()) {
                return new AIResponse(
                    content: '',
                    error: 'Claude API error: '.$response->status(),
                );
            }

            $data = $response->json();
            $usage = $data['usage'] ?? [];
            $content = '';
            $toolCalls = [];

            foreach ($data['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    /** @var array<string, mixed> $input */
                    $input = $block['input'] ?? [];
                    $toolCalls[] = new ToolCall(
                        id: $block['id'] ?? '',
                        name: $block['name'] ?? '',
                        arguments: $input,
                    );
                }
            }

            return new AIResponse(
                content: $content,
                toolCalls: $toolCalls,
                inputTokens: $usage['input_tokens'] ?? 0,
                outputTokens: $usage['output_tokens'] ?? 0,
                model: $this->model,
            );
        } catch (\Throwable $e) {
            return new AIResponse(content: '', error: $e->getMessage());
        }
    }

    public function provider(): string
    {
        return 'claude';
    }
}
