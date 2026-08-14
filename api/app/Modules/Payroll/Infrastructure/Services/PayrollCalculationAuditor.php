<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use Illuminate\Support\Str;

/**
 * Issue #1874 — audit & observabilité des calculs de paie.
 *
 * Enregistre UNE ligne par simulation et par run : contexte pays + version
 * des règles + période + entrées non sensibles + résultats agrégés +
 * identifiant de corrélation. Rien d'autre (pas de token, mot de passe ni
 * biométrie — le payload est whitelisté à la construction).
 */
class PayrollCalculationAuditor
{
    /**
     * @param  array{
     *     company_id: int|string,
     *     actor_id?: int|string|null,
     *     actor_role?: string|null,
     *     country_code: string,
     *     rules_version?: string|null,
     *     rules_period?: string|null,
     *     correlation_id: string,
     *     input_gross: float|int|string,
     *     result_net?: float|int|string|null,
     *     result_total_cost?: float|int|string|null,
     *     result_income_tax?: float|int|string|null,
     *     status?: string,
     *     error_class?: string|null,
     * }  $payload
     */
    public function record(array $payload): PayrollCalculationAudit
    {
        return PayrollCalculationAudit::create([
            'id' => (string) Str::uuid(),
            'company_id' => $payload['company_id'],
            'actor_id' => $payload['actor_id'] ?? null,
            'actor_role' => $payload['actor_role'] ?? null,
            'country_code' => strtoupper((string) $payload['country_code']),
            'rules_version' => $payload['rules_version'] ?? null,
            'rules_period' => $payload['rules_period'] ?? null,
            'correlation_id' => $payload['correlation_id'],
            'input_gross' => $payload['input_gross'],
            'result_net' => $payload['result_net'] ?? null,
            'result_total_cost' => $payload['result_total_cost'] ?? null,
            'result_income_tax' => $payload['result_income_tax'] ?? null,
            'status' => $payload['status'] ?? PayrollCalculationAudit::STATUS_OK,
            'error_class' => $payload['error_class'] ?? null,
        ]);
    }

    /**
     * Identifiant de corrélation pour un calcul (uuid v4).
     */
    public static function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
