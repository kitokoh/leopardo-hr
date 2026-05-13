<?php

namespace App\AI;

use App\AI\DTOs\AIRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

class AgentRunner
{
    private int $maxSteps;

    private Orchestrator $orchestrator;

    public function __construct(Orchestrator $orchestrator, int $maxSteps = 10)
    {
        $this->orchestrator = $orchestrator;
        $this->maxSteps = $maxSteps;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Employee $user, string $task, ?string $conversationId = null): array
    {
        $steps = [];
        $currentMessage = $task;
        $currentConversationId = $conversationId;

        for ($i = 0; $i < $this->maxSteps; $i++) {
            $response = $this->orchestrator->handle(new AIRequest(
                message: $currentMessage,
                userId: (int) $user->id,
                companyId: (string) $user->company_id,
                conversationId: is_numeric($currentConversationId) ? (int) $currentConversationId : null,
            ));
            $currentConversationId = (string) $response['conversation_id'];
            $toolsUsed = $response['tools_used'];

            $step = [
                'step' => $i + 1,
                'input' => $currentMessage,
                'response' => $response['response'],
                'tool_called' => $toolsUsed[0] ?? null,
                'tool_result' => null,
            ];
            $steps[] = $step;

            if ($toolsUsed === []) {
                break;
            }

            $currentMessage = 'Continue with the result of the previous tool call.';
        }

        $lastStep = $steps[array_key_last($steps)] ?? ['response' => ''];

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
            'final_response' => $lastStep['response'],
        ];
    }
}
