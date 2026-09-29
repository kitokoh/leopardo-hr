<?php

namespace App\AI;

use App\AI\Privacy\PrivacySanitizer;
use Illuminate\Support\Facades\DB;

class AIAuditLogger
{
    /**
     * Clés d'arguments d'outil dont la valeur est un secret/PII explicite :
     * la valeur entière est masquée au journal (A5 #6852, A6 #6853).
     *
     * @var list<string>
     */
    private const SENSITIVE_ARGUMENT_KEYS = [
        'email',
        'phone',
        'mobile',
        'whatsapp',
        'nid',
        'nin',
        'ssn',
        'national_id',
        'id_number',
        'passport',
        'iban',
        'bic',
        'password',
        'secret',
        'token',
        'access_token',
        'authorization',
    ];

    public function __construct(
        private readonly PrivacySanitizer $privacySanitizer = new PrivacySanitizer,
    ) {}

    /**
     * A5 (#6852) — journal d'exécution d'outil « via assistant IA ».
     *
     * Trace UNE étape de la chaîne conversation → action → effet :
     *  - exécution d'un outil de lecture (stage: executed) ;
     *  - proposition d'une écriture sensible (stage: confirmation_required,
     *    pending_action_id posé) ;
     *  - confirmation humaine → exécution (stage: executed, même
     *    pending_action_id — la chaîne est rejouable en joignant sur cet id) ;
     *  - refus humain (stage: rejected).
     *
     * Les arguments sont SANITISÉS (PII masquées, A6 #6853) avant écriture ;
     * chaque ligne porte le marqueur `source = 'assistant'`.
     *
     * @param  array<string, mixed>  $toolInput
     */
    public function logToolExecution(
        string $companyId,
        int $userId,
        ?int $conversationId,
        ?string $pendingActionId,
        string $toolName,
        array $toolInput,
        string $stage = 'executed',
        bool $success = true,
        ?string $resultSummary = null,
        ?string $error = null,
    ): void {
        DB::table('ai_tool_executions')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'pending_action_id' => $pendingActionId,
            'tool_name' => $toolName,
            'tool_input' => json_encode($this->sanitizeToolInput($toolInput), JSON_UNESCAPED_UNICODE) ?: null,
            'stage' => $stage,
            'success' => $success,
            'result_summary' => $this->truncate($resultSummary),
            'error' => $this->truncate($error),
            'source' => 'assistant',
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $toolInput
     * @return array<string, mixed>
     */
    private function sanitizeToolInput(array $toolInput): array
    {
        $sanitized = [];
        foreach ($toolInput as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $sanitized[$key] = $this->sanitizeToolInput($nested);
            } elseif (is_string($value)) {
                // Clés explicitement sensibles (email, n° national, IBAN…) :
                // valeur entièrement masquée — on ne garde ni la forme ni la
                // longueur. Les autres chaînes passent par les règles PII
                // textuelles (les dates ISO sont protégées, cf. sanitizeText).
                $sanitized[$key] = in_array(strtolower((string) $key), self::SENSITIVE_ARGUMENT_KEYS, true)
                    ? '[masqué]'
                    : $this->sanitizeText($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function truncate(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return mb_substr($this->sanitizeText($text), 0, 1000);
    }

    /**
     * Nettoie un texte (résumé, argument texte) : règles PII du
     * PrivacySanitizer, en PROTÉGEANT les dates/timestamps ISO et les UUID au
     * préalable — la règle « téléphone » du sanitizer (8-14
     * chiffres/séparateurs) matche les dates ISO (ex. 2026-06-10) et les UUID
     * (hex + tirets) ; les détruire rendrait le journal d'exécution
     * inexploitable (les dates sont l'essence des outils absence/shift, les
     * UUID relient la chaîne d'audit). Dates et UUID ne sont pas de la PII.
     */
    private function sanitizeText(string $text): string
    {
        $protectedTokens = [];
        $protected = preg_replace_callback(
            '/\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?|\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
            static function (array $match) use (&$protectedTokens): string {
                $protectedTokens[] = $match[0];

                return "\x1A".'P'.(count($protectedTokens) - 1)."\x1A";
            },
            $text,
        ) ?? $text;

        $cleaned = $this->privacySanitizer->sanitize($protected);

        foreach ($protectedTokens as $index => $token) {
            $cleaned = str_replace("\x1A".'P'.$index."\x1A", $token, $cleaned);
        }

        return $cleaned;
    }

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
        // BC-23-D10 (issue #6238) : workflow d'origine (null = chat direct).
        ?string $workflow = null,
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
            'workflow' => $workflow,
            'created_at' => now(),
        ]);
    }

    private function estimateCost(string $provider, string $model, int $inputTokens, int $outputTokens): int
    {
        // BOS-031 (#8221) — tarifs externalisés dans `config/ai.php` (costs.*).
        // Les valeurs par défaut de la config reproduisent à l'identique les
        // tarifs historiquement codés ici (parité vérifiée par test).
        /** @var array<string, array{input: float, output: float}> $rates */
        $rates = config('ai.costs.rates', []);
        /** @var array{input: float, output: float} $defaultRate */
        $defaultRate = config('ai.costs.default_rate', ['input' => 0.1, 'output' => 0.3]);

        $rate = $rates[$model] ?? $defaultRate;
        $costDollars = ($inputTokens / 100_000) * $rate['input'] + ($outputTokens / 100_000) * $rate['output'];

        return (int) round($costDollars * 100);
    }
}
