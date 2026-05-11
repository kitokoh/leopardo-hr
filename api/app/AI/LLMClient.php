<?php

namespace App\AI;

use App\AI\DTOs\AIResponse;

interface LLMClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function chat(array $messages, array $tools = []): AIResponse;

    public function provider(): string;
}
