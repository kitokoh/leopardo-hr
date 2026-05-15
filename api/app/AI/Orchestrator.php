<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\File;

class Orchestrator
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly IntentEngine $intentEngine,
        private readonly MemoryManager $memoryManager,
        private readonly AIAuditLogger $auditLogger,
        private readonly LLMClient $client,
    ) {}

    /**
     * @return array{conversation_id: int, response: string, tools_used: array<int, string>, tokens: array{input: int, output: int}}
     */
    public function handle(AIRequest $request): array
    {
        $startTime = hrtime(true);

        $conversation = $this->memoryManager->loadOrCreateConversation(
            $request->userId,
            $request->companyId,
            $request->conversationId,
            ['company_id' => $request->companyId],
        );

        $conversationId = $conversation['id'];
        $messages = $conversation['messages'];

        $systemPrompt = $this->loadSystemPrompt($request->companyId);
        $llmMessages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($messages as $msg) {
            $llmMessages[] = $msg;
        }
        $llmMessages[] = ['role' => 'user', 'content' => $request->message];

        $userRole = $this->resolveUserRole($request->userId, $request->companyId);
        $tools = $this->toolRegistry->getToolsAsLLMFormat($userRole, $request->companyId);

        $response = $this->client->chat($llmMessages, $tools);

        $toolsUsed = [];
        $pendingConfirmations = [];
        $maxIterations = 3;
        $iteration = 0;

        while ($response->hasToolCalls() && $iteration < $maxIterations) {
            $results = $this->intentEngine->executeToolCalls($response, $request->companyId, $request->userId);

            foreach ($results as $result) {
                $toolsUsed[] = $result->name;
                $decoded = json_decode($result->content, true);
                if (is_array($decoded) && ($decoded['status'] ?? null) === 'confirmation_required') {
                    $pendingConfirmations[] = $decoded;
                }
            }

            if ($pendingConfirmations !== []) {
                break;
            }

            if ($this->client->provider() === 'claude') {
                $llmMessages[] = [
                    'role' => 'assistant',
                    'content' => array_map(fn ($call) => [
                        'type' => 'tool_use',
                        'id' => $call->id,
                        'name' => $call->name,
                        'input' => $call->arguments,
                    ], $response->toolCalls),
                ];

                $llmMessages[] = [
                    'role' => 'user',
                    'content' => array_map(fn ($result) => [
                        'type' => 'tool_result',
                        'tool_use_id' => $result->toolCallId,
                        'content' => $result->content,
                        'is_error' => ! $result->success,
                    ], $results),
                ];
            } else {
                foreach ($results as $result) {
                    $llmMessages[] = ['role' => 'assistant', 'content' => "Tool call: {$result->name}"];
                    $llmMessages[] = ['role' => 'user', 'content' => "Tool result ({$result->name}): {$result->content}"];
                }
            }

            $response = $this->client->chat($llmMessages, $tools);
            $iteration++;
        }

        $messages[] = ['role' => 'user', 'content' => $request->message];
        $messages[] = ['role' => 'assistant', 'content' => $response->content];

        $totalTokens = $response->inputTokens + $response->outputTokens;
        $this->memoryManager->saveMessages($conversationId, $messages, $totalTokens);

        if (count($messages) <= 2) {
            $title = mb_substr($request->message, 0, 100);
            $this->memoryManager->updateTitle($conversationId, $title);
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $this->auditLogger->log(
            companyId: $request->companyId,
            userId: $request->userId,
            conversationId: $conversationId,
            prompt: $request->message,
            response: $response->content,
            toolsCalled: $toolsUsed,
            provider: $this->client->provider(),
            model: $response->model,
            inputTokens: $response->inputTokens,
            outputTokens: $response->outputTokens,
            durationMs: $durationMs,
            error: $response->error,
        );

        return [
            'conversation_id' => $conversationId,
            'response' => $response->content,
            'tools_used' => $toolsUsed,
            'pending_confirmations' => $pendingConfirmations,
            'tokens' => ['input' => $response->inputTokens, 'output' => $response->outputTokens],
        ];
    }

    private function loadSystemPrompt(string $companyId): string
    {
        /** @var string $path */
        $path = config('ai.system_prompt_path', resource_path('ai/system_prompt.md'));

        if (File::exists($path)) {
            return File::get($path);
        }

        return "Tu es l'assistant IA de Leopardo RH. Tu aides les managers et employes a gerer les ressources humaines. Reponds en francais sauf si l'utilisateur parle une autre langue. Sois concis et professionnel.";
    }

    private function resolveUserRole(int $userId, string $companyId): string
    {
        $employee = Employee::where('id', $userId)
            ->where('company_id', $companyId)
            ->first();

        if (! $employee) {
            return 'employee';
        }

        if ($employee->isManager()) {
            return 'manager';
        }

        return 'employee';
    }
}
