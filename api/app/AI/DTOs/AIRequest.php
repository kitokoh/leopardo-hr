<?php

namespace App\AI\DTOs;

class AIRequest
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function __construct(
        public readonly string $message,
        public readonly int $userId,
        public readonly string $companyId,
        public readonly ?int $conversationId = null,
        public readonly array $messages = [],
        public readonly array $tools = [],
        // BC-23-D10 (issue #6238) : nom du workflow d'origine (agent_run, …)
        // pour le suivi des budgets et l'analytics p95 par workflow.
        public readonly ?string $workflow = null,
    ) {}
}
