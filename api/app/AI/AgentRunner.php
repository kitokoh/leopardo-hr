<?php

namespace App\AI;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AgentRunner
{
    private int $maxSteps;

    private AIOrchestrator $orchestrator;

    public function __construct(AIOrchestrator $orchestrator, int $maxSteps = 10)
    {
        $this->orchestrator = $orchestrator;
        $this->maxSteps = $maxSteps;
    }

    /**
     * @param  User  $user
     * @return array<string, mixed>
     */
    public function execute($user, string $task, ?string $conversationId = null): array
    {
        $steps = [];
        $currentMessage = $task;
        $currentConversationId = $conversationId;

        for ($i = 0; $i < $this->maxSteps; $i++) {
            $response = $this->orchestrator->handle($user, $currentMessage, $currentConversationId);
            $currentConversationId = $response['conversation_id'] ?? $currentConversationId;

            $step = [
                'step' => $i + 1,
                'input' => $currentMessage,
                'response' => $response['response'] ?? '',
                'tool_called' => $response['tool_called'] ?? null,
                'tool_result' => $response['tool_result'] ?? null,
            ];
            $steps[] = $step;

            if (empty($response['tool_called'])) {
                break;
            }

            $currentMessage = 'Continue with the result of the previous tool call. The tool returned: '.json_encode($response['tool_result'] ?? []);
        }

        Log::info('Agent completed', [
            'task' => $task,
            'steps' => count($steps),
            'user_id' => $user->id,
        ]);

        return [
            'task' => $task,
            'conversation_id' => $currentConversationId,
            'steps' => $steps,
            'total_steps' => count($steps),
            'final_response' => end($steps)['response'] ?? '',
        ];
    }
}
