<?php

namespace App\AI;

use App\AI\DTOs\AIRequest;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Log;

class AgentRunner
{
    private int $maxSteps;

    private Orchestrator $orchestrator;

    // BC-23-D10 (issue #6238) : nom de workflow tracé dans l'audit AI.
    public const WORKFLOW_NAME = 'agent_run';

    public function __construct(
        Orchestrator $orchestrator,
        int $maxSteps = 10,
        // BC-23-D10 (issue #6238) : budgets de tokens versionnés.
        private readonly TokenBudgetGuard $tokenBudgetGuard = new TokenBudgetGuard,
    ) {
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

        // BC-23-D10 : cumul de tokens de l'exécution d'agent (toutes étapes),
        // borné par `ai.budgets.max_tokens_per_workflow` (fail-closed).
        $cumulativeTokens = 0;

        for ($i = 0; $i < $this->maxSteps; $i++) {
            $response = $this->orchestrator->handle(new AIRequest(
                message: $currentMessage,
                userId: (int) $user->id,
                companyId: (string) $user->company_id,
                conversationId: is_numeric($currentConversationId) ? (int) $currentConversationId : null,
                workflow: self::WORKFLOW_NAME,
            ));
            $currentConversationId = (string) $response['conversation_id'];
            $toolsUsed = $response['tools_used'];

            $cumulativeTokens += ($response['tokens']['input'] ?? 0) + ($response['tokens']['output'] ?? 0);
            $this->tokenBudgetGuard->assertWorkflowWithinBudget($cumulativeTokens);

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
            'total_tokens' => $cumulativeTokens,
        ]);

        return [
            'task' => $task,
            'conversation_id' => $currentConversationId,
            'steps' => $steps,
            'total_steps' => count($steps),
            'final_response' => $lastStep['response'],
            'total_tokens' => $cumulativeTokens,
        ];
    }
}
