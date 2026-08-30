<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\TokenBudgetExceededException;

/**
 * BC-23-D10 (issue #6238) — budgets de tokens AI versionnés.
 *
 * Source de vérité : `config/ai.php` (`budgets.*`, overridable par env).
 * Les limites sont vérifiées avant/après chaque appel LLM :
 * - `assertRequestWithinBudget`   : cumul de tokens d'UNE requête (chat, step agent) ;
 * - `assertWorkflowWithinBudget`  : cumul de toutes les étapes d'une exécution d'agent ;
 * - `contextBudgetExceeded`       : cumul d'une conversation (historique + échanges).
 *
 * Le comportement est fail-closed : au dépassement, une
 * `TokenBudgetExceededException` est levée et aucun effet de bord ne subsiste.
 */
class TokenBudgetGuard
{
    public function maxTokensPerRequest(): int
    {
        return max(1, (int) config('ai.budgets.max_tokens_per_request', 4096));
    }

    public function maxContextTokens(): int
    {
        return max(1, (int) config('ai.budgets.max_context_tokens', 32768));
    }

    public function maxTokensPerWorkflow(): int
    {
        return max(1, (int) config('ai.budgets.max_tokens_per_workflow', 16384));
    }

    /**
     * @throws TokenBudgetExceededException
     */
    public function assertRequestWithinBudget(int $inputTokens, int $outputTokens): void
    {
        $total = max(0, $inputTokens) + max(0, $outputTokens);
        $limit = $this->maxTokensPerRequest();

        if ($total > $limit) {
            throw new TokenBudgetExceededException(
                sprintf('AI request token budget exceeded (%d > %d).', $total, $limit)
            );
        }
    }

    /**
     * @throws TokenBudgetExceededException
     */
    public function assertWorkflowWithinBudget(int $cumulativeTokens): void
    {
        $limit = $this->maxTokensPerWorkflow();

        if ($cumulativeTokens > $limit) {
            throw new TokenBudgetExceededException(
                sprintf('AI workflow token budget exceeded (%d > %d).', $cumulativeTokens, $limit)
            );
        }
    }

    public function contextBudgetExceeded(int $conversationTokenCount): bool
    {
        return $conversationTokenCount > $this->maxContextTokens();
    }
}
