<?php

namespace App\AI;

use Illuminate\Support\Facades\DB;

class AIAuditLogger
{
    /**
     * @param  array<int, mixed>  $toolsCalled
     */
    public function log(
        string $companyId,
        int $userId,
        ?int $conversationId,
        string $prompt,
        string $response,
        array $toolsCalled,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $durationMs,
        ?string $error = null,
    ): void {
        $costCents = $this->estimateCost($provider, $model, $inputTokens, $outputTokens);

        DB::table('ai_audit_logs')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'prompt' => mb_substr($prompt, 0, 10000),
            'response' => mb_substr($response, 0, 10000),
            'tools_called' => json_encode($toolsCalled),
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'duration_ms' => $durationMs,
            'error' => $error,
            'created_at' => now(),
        ]);
    }

    private function estimateCost(string $provider, string $model, int $inputTokens, int $outputTokens): int
    {
        $rates = [
            'gpt-4o' => ['input' => 0.25, 'output' => 1.0],
            'gpt-4o-mini' => ['input' => 0.015, 'output' => 0.06],
            'claude-sonnet-4-20250514' => ['input' => 0.3, 'output' => 1.5],
        ];

        $rate = $rates[$model] ?? ['input' => 0.1, 'output' => 0.3];
        $costDollars = ($inputTokens / 100_000) * $rate['input'] + ($outputTokens / 100_000) * $rate['output'];

        return (int) round($costDollars * 100);
    }
}
