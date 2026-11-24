<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #9874 (R4) — coordinateur du paiement en masse, porté par PostgreSQL.
 *
 * Remplace le verrou Redis (`SET NX EX` sur `bulk_pay:run:{id}`) qui faisait de
 * Redis un point unique de défaillance sur un mouvement d'argent : quand Redis
 * tombait, `POST /bulk-pay` renvoyait 503 et la paie de tous les tenants
 * s'arrêtait, alors que PostgreSQL est déjà dans le chemin critique.
 *
 * L'exclusion mutuelle vient ici d'une contrainte `UNIQUE (payroll_run_id)` :
 * deux `INSERT` concurrents ne peuvent pas réussir tous les deux, donc un seul
 * dispatch gagne — sans dépendre d'un cache.
 *
 * Le bail `expires_at` remplace la logique « statut stale » du contrôleur : un
 * claim dont le worker est mort peut être repris, donc un run n'est jamais
 * bloqué définitivement.
 */
class BulkPaymentCoordinator
{
    /** Durée du bail d'un claim. Aligné sur le timeout du job (300 s) + marge. */
    public const LEASE_SECONDS = 21600;

    /** Statuts considérés comme « un job tourne encore ». */
    public const ACTIVE_STATUSES = ['starting', 'processing'];

    /**
     * Tente de prendre le claim d'un run.
     *
     * @return array{acquired: bool, status: string|null, progress: array<string, mixed>|null}
     */
    public function claim(int $payrollRunId, ?string $companyId, ?int $triggeredBy): array
    {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::LEASE_SECONDS);

        // 1) Insertion atomique : la contrainte UNIQUE porte l'exclusion.
        //
        // On passe par une transaction imbriquée : sous PostgreSQL, une
        // violation de contrainte AVORTE la transaction courante (25P02).
        // En test (et dans tout appelant déjà transactionnel) il faut donc un
        // SAVEPOINT pour pouvoir inspecter l'existant après l'échec.
        try {
            DB::transaction(function () use ($payrollRunId, $companyId, $triggeredBy, $now, $expiresAt): void {
                DB::table('bulk_payment_claims')->insert([
                    'payroll_run_id' => $payrollRunId,
                    'company_id' => $companyId,
                    'status' => 'starting',
                    'done' => 0,
                    'total' => 0,
                    'failed' => 0,
                    'triggered_by' => $triggeredBy,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

            return ['acquired' => true, 'status' => 'starting', 'progress' => null];
        } catch (Throwable $e) {
            // Violation d'unicité = un claim existe déjà → on l'inspecte.
            $existing = DB::table('bulk_payment_claims')
                ->where('payroll_run_id', $payrollRunId)
                ->first();

            if ($existing === null) {
                // La contrainte a échoué pour une autre raison (connexion,
                // schéma) : on ne prétend pas avoir acquis le claim.
                Log::error('payroll.bulk_payment.claim_failed', [
                    'payroll_run_id' => $payrollRunId,
                    'error' => $e->getMessage(),
                ]);

                return ['acquired' => false, 'status' => 'unavailable', 'progress' => null];
            }

            /** @var array<string, mixed> $existingRow */
            $existingRow = (array) $existing;
            $status = is_string($existingRow['status'] ?? null) ? $existingRow['status'] : '';
            $expiresAtRaw = $existingRow['expires_at'] ?? null;
            $expired = is_string($expiresAtRaw)
                ? CarbonImmutable::parse($expiresAtRaw)->isPast()
                : true;

            // Un job ACTIF et NON EXPIRÉ tient le run : on refuse (409).
            if (in_array($status, self::ACTIVE_STATUSES, true) && ! $expired) {
                return [
                    'acquired' => false,
                    'status' => $status,
                    'progress' => $this->progressOf($existing),
                ];
            }

            // Bail expiré (worker mort) ou statut terminal : on reprend la main
            // avec un UPDATE conditionnel — deux repreneurs concurrents ne
            // peuvent pas gagner tous les deux.
            $reclaimed = DB::table('bulk_payment_claims')
                ->where('payroll_run_id', $payrollRunId)
                ->where(function ($q) use ($now): void {
                    /** @var \Illuminate\Database\Query\Builder $q */
                    $q->where('expires_at', '<', $now)
                        ->orWhereIn('status', ['completed', 'completed_with_errors', 'failed']);
                })
                ->update([
                    'status' => 'starting',
                    'done' => 0,
                    'total' => 0,
                    'failed' => 0,
                    'failures' => null,
                    'triggered_by' => $triggeredBy,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now,
                ]);

            if ($reclaimed === 1) {
                return ['acquired' => true, 'status' => 'starting', 'progress' => null];
            }

            return [
                'acquired' => false,
                'status' => $status,
                'progress' => $this->progressOf($existing),
            ];
        }
    }

    /**
     * Met à jour l'avancement d'un run (remplace `updateProgress()` Redis).
     *
     * @param  array<int, array{pay_slip_id: int, employee_id: int, error: string}>  $failures
     */
    public function advance(int $payrollRunId, string $status, int $done, int $total = 0, array $failures = []): void
    {
        try {
            DB::table('bulk_payment_claims')
                ->where('payroll_run_id', $payrollRunId)
                ->update([
                    'status' => $status,
                    'done' => $done,
                    'total' => $total,
                    'failed' => count($failures),
                    'failures' => $failures === [] ? null : json_encode($failures),
                    'updated_at' => CarbonImmutable::now(),
                ]);
        } catch (Throwable $e) {
            // L'avancement n'est pas critique : ne jamais faire échouer un
            // paiement réellement effectué parce que le suivi a raté.
            Log::warning('payroll.bulk_payment.progress_failed', [
                'payroll_run_id' => $payrollRunId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Libère le claim (échec définitif ou redispatch propre).
     */
    public function release(int $payrollRunId): void
    {
        try {
            DB::table('bulk_payment_claims')
                ->where('payroll_run_id', $payrollRunId)
                ->update([
                    'status' => 'failed',
                    'expires_at' => CarbonImmutable::now()->subSecond(),
                    'updated_at' => CarbonImmutable::now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('payroll.bulk_payment.release_failed', [
                'payroll_run_id' => $payrollRunId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lit l'avancement d'un run — sans Redis.
     *
     * @return array<string, mixed>|null  null si aucun claim n'existe
     */
    public function status(int $payrollRunId): ?array
    {
        $row = DB::table('bulk_payment_claims')
            ->where('payroll_run_id', $payrollRunId)
            ->first();

        return $row === null ? null : $this->progressOf($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function progressOf(object $row): array
    {
        /** @var array<string, mixed> $data */
        $data = (array) $row;

        $failures = $data['failures'] ?? null;

        return [
            'status' => is_string($data['status'] ?? null) ? $data['status'] : '',
            'done' => is_numeric($data['done'] ?? null) ? (int) $data['done'] : 0,
            'total' => is_numeric($data['total'] ?? null) ? (int) $data['total'] : 0,
            'failed' => is_numeric($data['failed'] ?? null) ? (int) $data['failed'] : 0,
            'failures' => is_string($failures) ? (json_decode($failures, true) ?? []) : ($failures ?? []),
            'expires_at' => is_string($data['expires_at'] ?? null) ? $data['expires_at'] : '',
            'updated_at' => is_string($data['updated_at'] ?? null) ? $data['updated_at'] : '',
        ];
    }
}
