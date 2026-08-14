<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issue #1874 — enregistrement d'audit des calculs de paie.
 *
 * Point d'entrée UNIQUE d'écriture dans `payroll_calculation_audits`
 * (utilisé par `PayrollCalculator::calculateRun()` et les contrôleurs de
 * simulation). Garanties :
 *   - ne contient JAMAIS de données individuelles ni de secrets (uniquement
 *     des paramètres agrégés et des résultats agrégés) ;
 *   - ne JAMAIS lever d'exception : l'audit ne doit pas casser la paie
 *     (même discipline que `DataAccessAuditLogger` — catch + report) ;
 *   - l'acteur est résolu automatiquement (utilisateur authentifié ou job).
 */
class PayrollCalculationAuditRecorder
{
    /**
     * Enregistre un run de paie calculé avec succès (résultats agrégés).
     */
    public function recordRunSuccess(PayrollRun $run, string $correlationId): void
    {
        $this->record([
            'correlation_id' => $correlationId,
            'company_id' => $run->company_id,
            'country_code' => (string) $run->country_code,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'rules_version' => $run->rules_version,
            'rules_identifier' => $run->rules_identifier,
            // Agrégats uniquement — jamais de salaires individuels.
            'input_snapshot' => [
                'employee_count' => (int) $run->employee_count,
                'total_gross' => round((float) $run->total_gross, 2),
                'run_type' => $run->type,
            ],
            'result_snapshot' => [
                'total_gross' => round((float) $run->total_gross, 2),
                'total_deductions' => round((float) $run->total_deductions, 2),
                'total_net' => round((float) $run->total_net, 2),
                'total_employer_cost' => round((float) $run->total_employer_cost, 2),
                'employee_count' => (int) $run->employee_count,
                'calculated_at' => $run->calculated_at?->toIso8601String(),
            ],
            'status' => PayrollCalculationAudit::STATUS_SUCCESS,
            'error_message' => null,
        ]);
    }

    /**
     * Enregistre un run de paie en échec (statut de résolution mappé).
     *
     * @param  PayrollCalculationAudit::STATUS_*  $status
     */
    public function recordRunFailure(PayrollRun $run, string $correlationId, string $status, Throwable $exception): void
    {
        $this->record([
            'correlation_id' => $correlationId,
            'company_id' => $run->company_id,
            'country_code' => (string) $run->country_code,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'rules_version' => $run->rules_version,
            'rules_identifier' => $run->rules_identifier,
            // Agrégats connus au moment de l'échec (le calcul n'a pas abouti).
            'input_snapshot' => [
                'employee_count' => (int) $run->employee_count,
                'total_gross' => round((float) $run->total_gross, 2),
                'run_type' => $run->type,
            ],
            'result_snapshot' => null,
            'status' => $status,
            // Classe d'exception uniquement — jamais le message brut (peut
            // contenir des données d'environnement/requête).
            'error_message' => $this->safeExceptionLabel($exception),
        ]);
    }

    /**
     * Enregistre une simulation de paie (POST /cotisation-simulation ou
     * /payroll/simulate) — succès ou échec de résolution.
     *
     * @param  array<string, mixed>  $input  paramètres agrégés non sensibles
     * @param  array<string, mixed>|null  $result  résultats agrégés (null en échec)
     * @param  PayrollCalculationAudit::STATUS_*  $status
     */
    public function recordSimulation(
        string $correlationId,
        ?string $companyId,
        string $countryCode,
        array $input,
        ?array $result,
        string $status,
        ?string $errorMessage = null,
        ?string $rulesVersion = null,
        ?string $rulesIdentifier = null,
    ): void {
        $this->record([
            'correlation_id' => $correlationId,
            'company_id' => $companyId,
            'country_code' => $countryCode,
            'period_start' => null,
            'period_end' => null,
            'rules_version' => $rulesVersion,
            'rules_identifier' => $rulesIdentifier,
            'input_snapshot' => $input,
            'result_snapshot' => $result,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        try {
            [$actorType, $actorId] = $this->resolveActor();
            $audit = PayrollCalculationAudit::query()->create([
                ...$attributes,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            // Observabilité (issue #1874) : le lien log ↔ audit est tangible —
            // le correlation_id est aussi propagé via Log::withContext par les
            // appelants. Contenu non sensible (agrégats/contexte uniquement).
            Log::info('payroll.audit.recorded', [
                'correlation_id' => $audit->correlation_id,
                'status' => $audit->status,
                'country_code' => $audit->country_code,
                'period_start' => $audit->period_start?->toDateString(),
                'actor_type' => $audit->actor_type,
            ]);
        } catch (Throwable $exception) {
            // L'audit ne doit JAMAIS faire échouer un calcul de paie.
            report($exception);
        }
    }

    /**
     * Acteur du calcul : utilisateur authentifié (employee manager ou
     * super-admin) sinon job asynchrone.
     *
     * @return array{0: string, 1: int|null}
     */
    private function resolveActor(): array
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('super_admin_api')->user();

        if ($user instanceof Employee || $user instanceof SuperAdmin) {
            return [PayrollCalculationAudit::ACTOR_USER, (int) $user->id];
        }

        return [PayrollCalculationAudit::ACTOR_JOB, null];
    }

    /**
     * Libellé d'erreur sûr pour l'audit : classe d'exception uniquement
     * (jamais le message brut, qui peut contenir des données sensibles).
     */
    private function safeExceptionLabel(Throwable $exception): string
    {
        return (new \ReflectionClass($exception))->getShortName();
    }
}
