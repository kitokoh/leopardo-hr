<?php

namespace App\AI\DTOs;

class ToolResult
{
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $name,
        public readonly string $content,
        public readonly bool $success = true,
    ) {}
}
