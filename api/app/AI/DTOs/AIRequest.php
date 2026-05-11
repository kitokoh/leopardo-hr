<?php

namespace App\AI\DTOs;

class AIRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function __construct(
        public readonly string $message,
        public readonly int $userId,
        public readonly string $companyId,
        public readonly ?int $conversationId = null,
        public readonly array $messages = [],
        public readonly array $tools = [],
    ) {}
}
