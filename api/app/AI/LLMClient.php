<?php

namespace App\AI;

use App\AI\DTOs\AIResponse;

interface LLMClient
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $responseFormat  BOS-031 (#8221) — `response_format`
     *                                                     optionnel transmis tel quel aux
     *                                                     providers qui le supportent
     *                                                     (OpenAI/Groq) ; ignoré ailleurs.
     */
    public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse;

    public function provider(): string;
}
