<?php

namespace App\AI\DTOs;

class AIResponse
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly string $model = '',
        public readonly ?string $error = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
