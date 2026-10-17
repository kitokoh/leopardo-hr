<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Exceptions\FarmOperationTransitionException;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Transition d'état d'une opération (start / complete / skip / cancel).
 *
 * Applique la machine à états FERMÉE de {@see FarmOperationStatus} :
 *   - une transition non autorisée est refusée (422) ;
 *   - l'annulation d'une opération démarrée exige un motif ;
 *   - une opération `Done` est IMMUABLE : la correction passe par une action
 *     dédiée auditée, jamais par un PATCH direct.
 */
final class TransitionFarmOperation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        FarmOperation $operation,
        FarmOperationStatus $target,
        array $payload = [],
        ?int $employeeId = null,
    ): FarmOperation {
        $current = $operation->statusEnum();

        if (! $current->canTransitionTo($target)) {
            throw FarmOperationTransitionException::notAllowed($current->value, $target->value);
        }

        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        if ($target === FarmOperationStatus::Cancelled && $current === FarmOperationStatus::InProgress && ($reason === null || $reason === '')) {
            throw FarmOperationTransitionException::reasonRequired($target->value);
        }

        $now = Carbon::now();

        $attributes = [
            'status' => $target->value,
        ];

        if ($target === FarmOperationStatus::InProgress) {
            $attributes['started_at'] = $operation->started_at ?? $now;
        }

        if ($target->requiresFinishedAt()) {
            $attributes['finished_at'] = $now;
        }

        if ($target === FarmOperationStatus::Done) {
            $attributes['progress_percent'] = isset($payload['progress_percent'])
                ? (string) $payload['progress_percent']
                : '100.00';

            if (isset($payload['quantity_value'])) {
                $attributes['quantity_value'] = $payload['quantity_value'];
            }

            if (isset($payload['quantity_unit'])) {
                $attributes['quantity_unit'] = $payload['quantity_unit'];
            }

            if ($employeeId !== null) {
                $attributes['responsible_id'] = $operation->responsible_id ?? $employeeId;
            }
        }

        if (is_string($reason) && $reason !== '') {
            $attributes['notes'] = trim(($operation->notes ?? '')."\n".$reason);
        }

        $operation->fill($attributes);
        $operation->save();

        return $operation->refresh();
    }
}
