<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Enums\FarmOperationType;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Planification d'une opération agricole (intervention).
 *
 * IDEMPOTENT : si un `client_operation_id` est fourni et qu'une opération
 * existe déjà pour ce tenant, elle est renvoyée telle quelle — le rejeu d'une
 * écriture mobile ou hors ligne ne crée JAMAIS de doublon.
 */
final class ScheduleFarmOperation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Company $company, FarmSite $site, array $payload, ?int $employeeId = null): FarmOperation
    {
        $clientOperationId = isset($payload['client_operation_id']) && $payload['client_operation_id'] !== ''
            ? (string) $payload['client_operation_id']
            : null;

        if ($clientOperationId !== null) {
            $existing = FarmOperation::query()
                ->where('client_operation_id', $clientOperationId)
                ->first();

            if ($existing instanceof FarmOperation) {
                return $existing;
            }
        }

        $type = FarmOperationType::from((string) $payload['op_type']);

        return FarmOperation::query()->create([
            'company_id' => $company->id,
            'farm_site_id' => $site->id,
            'parcel_id' => $payload['parcel_id'] ?? null,
            'crop_cycle_id' => $payload['crop_cycle_id'] ?? null,
            'op_type' => $type->value,
            'title' => (string) ($payload['title'] ?? $type->value),
            'instructions' => $payload['instructions'] ?? null,
            'status' => FarmOperationStatus::Planned->value,
            'priority' => (string) ($payload['priority'] ?? 'normal'),
            'planned_start_at' => isset($payload['planned_start_at'])
                ? Carbon::parse((string) $payload['planned_start_at'])
                : null,
            'planned_end_at' => isset($payload['planned_end_at'])
                ? Carbon::parse((string) $payload['planned_end_at'])
                : null,
            'assigned_to' => $payload['assigned_to'] ?? null,
            'farm_team_id' => $payload['farm_team_id'] ?? null,
            'responsible_id' => $payload['responsible_id'] ?? $employeeId,
            'quantity_value' => $payload['quantity_value'] ?? null,
            'quantity_unit' => $payload['quantity_unit'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'client_operation_id' => $clientOperationId,
            'created_by' => $employeeId,
        ]);
    }
}
