<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Modules\FarmOs\Domain\Exceptions\FarmEquipmentException;
use App\Modules\FarmOs\Domain\Models\FarmEquipment;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentBreakdown;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentMaintenance;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentUsageLog;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Journal d'utilisation et de maintenance du materiel.
 *
 * Invariants (exigences de la mission) :
 *   - SAISIE MANUELLE UNIQUEMENT : `meter_source` ne peut valoir que `manual`
 *     ou `imported`. Aucune valeur n'est presentee comme automatiquement
 *     mesuree — Farm OS n'a aucune integration de telemetrie agricole ;
 *   - un compteur NE RECULE PAS : un releve inferieur au dernier connu est
 *     refuse (exception + 422), sauf remplacement explicite du compteur ;
 *   - un materiel SANS compteur (`meter_type = none`) refuse tout releve : on
 *     n'ecrit pas un compteur dans un registre qui n'en suit pas ;
 *   - IDEMPOTENT : le rejeu d'une ecriture mobile/hors ligne portant le meme
 *     `client_*_id` renvoie la ligne existante au lieu d'en creer une seconde ;
 *   - un entretien CLOS n'est jamais rouvert (historique de maintenance fiable) ;
 *   - un cout INCONNU reste `null` : il n'est jamais remplace par zero, sinon la
 *     rentabilite afficherait une marge fausse.
 *
 * Purete des couches (issue #6568) : aucune facade Laravel ici. La transaction
 * est portee par la connexion du modele (`getConnection()`) — `DB` et `Schema`
 * sont reserves aux couches Interfaces/Infrastructure.
 */
final class FarmEquipmentService
{
    /**
     * Releve de compteur (utilisation) + mise a jour du dernier compteur connu.
     *
     * @param  numeric-string|string  $meterValue
     */
    public function recordUsage(
        Company $company,
        FarmEquipment $equipment,
        string $meterValue,
        ?FarmMeterSource $source = null,
        ?int $farmOperationId = null,
        ?int $parcelId = null,
        ?int $operatorId = null,
        ?string $reason = null,
        ?int $recordedBy = null,
        ?string $clientUsageId = null,
        ?Carbon $occurredAt = null,
    ): FarmEquipmentUsageLog {
        $this->assertBelongsToCompany($company, $equipment);

        if (! $equipment->statusEnum()->acceptsUsage()) {
            throw FarmEquipmentException::equipmentRetired($equipment->id);
        }

        if (! $equipment->tracksMeter()) {
            throw FarmEquipmentException::meterNotTracked($equipment->id);
        }

        $value = $this->assertNotNegative($meterValue);
        $meterSource = $source ?? FarmMeterSource::Manual;

        $connection = FarmEquipmentUsageLog::query()->getConnection();
        $now = Carbon::now();
        $occurred = $occurredAt ?? $now;

        return $connection->transaction(function () use (
            $company, $equipment, $value, $meterSource, $farmOperationId, $parcelId,
            $operatorId, $reason, $recordedBy, $clientUsageId, $occurred
        ): FarmEquipmentUsageLog {
            // Idempotence terrain : un rejeu renvoie le releve deja ecrit.
            if ($clientUsageId !== null) {
                /** @var FarmEquipmentUsageLog|null $existing */
                $existing = FarmEquipmentUsageLog::query()
                    ->where('company_id', $company->id)
                    ->where('client_usage_id', $clientUsageId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var FarmEquipment $locked */
            $locked = FarmEquipment::query()
                ->where('company_id', $company->id)
                ->whereKey($equipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = $locked->meter_value !== null ? (float) $locked->meter_value : null;
            $given = (float) $value;

            if ($previous !== null && $given < $previous) {
                throw FarmEquipmentException::meterRegression(
                    number_format($previous, 2, '.', ''),
                    number_format($given, 2, '.', ''),
                );
            }

            $log = new FarmEquipmentUsageLog([
                'farm_equipment_id' => $locked->id,
                'meter_value' => number_format($given, 2, '.', ''),
                // NULL au premier releve : on ne suppose pas un compteur a zero.
                'meter_delta' => $previous === null
                    ? null
                    : number_format($given - $previous, 2, '.', ''),
                'meter_source' => $meterSource->value,
                'farm_operation_id' => $farmOperationId,
                'parcel_id' => $parcelId,
                'operator_id' => $operatorId,
                'reason' => $reason,
                'recorded_by' => $recordedBy,
                'client_usage_id' => $clientUsageId,
                'occurred_at' => $occurred,
            ]);
            $log->company_id = $company->id;
            $log->save();

            $locked->meter_value = number_format($given, 2, '.', '');
            $locked->meter_source = $meterSource->value;
            $locked->save();

            return $log;
        });
    }

    /**
     * Remplacement explicite du compteur (changement de compteur, correction
     * d'une saisie erronee). C'est la SEULE voie qui autorise une valeur
     * inferieure : elle est tracee comme un releve `manual` avec un motif.
     *
     * @param  numeric-string|string  $meterValue
     */
    public function resetMeter(
        Company $company,
        FarmEquipment $equipment,
        string $meterValue,
        string $reason,
        ?int $recordedBy = null,
        ?string $clientUsageId = null,
    ): FarmEquipmentUsageLog {
        $this->assertBelongsToCompany($company, $equipment);

        if (! $equipment->tracksMeter()) {
            throw FarmEquipmentException::meterNotTracked($equipment->id);
        }

        $value = $this->assertNotNegative($meterValue);

        $connection = FarmEquipmentUsageLog::query()->getConnection();
        $now = Carbon::now();

        return $connection->transaction(function () use (
            $company, $equipment, $value, $reason, $recordedBy, $clientUsageId, $now
        ): FarmEquipmentUsageLog {
            if ($clientUsageId !== null) {
                /** @var FarmEquipmentUsageLog|null $existing */
                $existing = FarmEquipmentUsageLog::query()
                    ->where('company_id', $company->id)
                    ->where('client_usage_id', $clientUsageId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var FarmEquipment $locked */
            $locked = FarmEquipment::query()
                ->where('company_id', $company->id)
                ->whereKey($equipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $log = new FarmEquipmentUsageLog([
                'farm_equipment_id' => $locked->id,
                'meter_value' => number_format((float) $value, 2, '.', ''),
                // Un remplacement de compteur n'est PAS un ecart d'utilisation :
                // le delta reste `null` pour ne pas fausser les heures totales.
                'meter_delta' => null,
                'meter_source' => FarmMeterSource::Manual->value,
                'reason' => $reason,
                'recorded_by' => $recordedBy,
                'client_usage_id' => $clientUsageId,
                'occurred_at' => $now,
            ]);
            $log->company_id = $company->id;
            $log->save();

            $locked->meter_value = number_format((float) $value, 2, '.', '');
            $locked->meter_source = FarmMeterSource::Manual->value;
            $locked->save();

            return $log;
        });
    }

    /**
     * Cloture d'un entretien planifie : il devient `done` et n'est plus
     * modifiable. Un entretien deja clos est refuse (jamais de reouverture).
     *
     * @param  numeric-string|string|null  $costAmount
     */
    public function completeMaintenance(
        Company $company,
        FarmEquipmentMaintenance $maintenance,
        ?string $costAmount = null,
        ?string $currency = null,
        ?Carbon $performedAt = null,
        ?string $meterValueAt = null,
    ): FarmEquipmentMaintenance {
        if ($maintenance->company_id !== null && $maintenance->company_id !== $company->id) {
            throw FarmEquipmentException::equipmentNotInCompany($maintenance->farm_equipment_id);
        }

        if (! $maintenance->isOpen()) {
            throw FarmEquipmentException::maintenanceAlreadyClosed($maintenance->id);
        }

        $maintenance->status = FarmMaintenanceStatus::Done->value;
        $maintenance->performed_at = $performedAt ?? Carbon::now();

        if ($costAmount !== null) {
            $maintenance->cost_amount = $this->assertNotNegative($costAmount);
        }

        if ($currency !== null) {
            $maintenance->currency = strtoupper($currency);
        }

        if ($meterValueAt !== null) {
            $maintenance->meter_value_at = $this->assertNotNegative($meterValueAt);
        }

        $maintenance->save();

        return $maintenance;
    }

    /**
     * Resolution d'une panne : `resolved_at` est pose une seule fois.
     *
     * @param  numeric-string|string|null  $costAmount
     */
    public function resolveBreakdown(
        Company $company,
        FarmEquipmentBreakdown $breakdown,
        ?string $costAmount = null,
        ?string $currency = null,
        ?string $downtimeHours = null,
        ?Carbon $resolvedAt = null,
    ): FarmEquipmentBreakdown {
        if ($breakdown->company_id !== null && $breakdown->company_id !== $company->id) {
            throw FarmEquipmentException::equipmentNotInCompany($breakdown->farm_equipment_id);
        }

        // Idempotent : une panne deja resolue n'est pas re-resolue (on ne
        // reecrit pas une date de resolution deja posee).
        if ($breakdown->isResolved()) {
            return $breakdown;
        }

        $breakdown->resolved_at = $resolvedAt ?? Carbon::now();

        if ($costAmount !== null) {
            $breakdown->cost_amount = $this->assertNotNegative($costAmount);
        }

        if ($currency !== null) {
            $breakdown->currency = strtoupper($currency);
        }

        if ($downtimeHours !== null) {
            $breakdown->downtime_hours = $this->assertNotNegative($downtimeHours);
        }

        $breakdown->save();

        return $breakdown;
    }

    /**
     * Couts de maintenance CONNUS d'un materiel, par nature.
     *
     * Honnetete : on renvoie separement le total des couts RENSEIGNES et le
     * nombre de lignes dont le cout est INCONNU. Un total partiel n'est jamais
     * presente comme un cout complet.
     *
     * @return array{maintenance_known: string, maintenance_unknown_count: int, breakdown_known: string, breakdown_unknown_count: int, total_known: string, is_complete: bool}
     */
    public function knownCosts(Company $company, FarmEquipment $equipment): array
    {
        $this->assertBelongsToCompany($company, $equipment);

        $maintenances = FarmEquipmentMaintenance::query()
            ->where('company_id', $company->id)
            ->where('farm_equipment_id', $equipment->id)
            ->get();

        $breakdowns = FarmEquipmentBreakdown::query()
            ->where('company_id', $company->id)
            ->where('farm_equipment_id', $equipment->id)
            ->get();

        $maintenanceKnown = 0.0;
        $maintenanceUnknown = 0;

        foreach ($maintenances as $maintenance) {
            if ($maintenance->cost_amount === null) {
                $maintenanceUnknown++;

                continue;
            }

            $maintenanceKnown += (float) $maintenance->cost_amount;
        }

        $breakdownKnown = 0.0;
        $breakdownUnknown = 0;

        foreach ($breakdowns as $breakdown) {
            if ($breakdown->cost_amount === null) {
                $breakdownUnknown++;

                continue;
            }

            $breakdownKnown += (float) $breakdown->cost_amount;
        }

        return [
            'maintenance_known' => number_format($maintenanceKnown, 2, '.', ''),
            'maintenance_unknown_count' => $maintenanceUnknown,
            'breakdown_known' => number_format($breakdownKnown, 2, '.', ''),
            'breakdown_unknown_count' => $breakdownUnknown,
            'total_known' => number_format($maintenanceKnown + $breakdownKnown, 2, '.', ''),
            // Le total n'est complet que si AUCUN cout n'est manquant.
            'is_complete' => $maintenanceUnknown === 0 && $breakdownUnknown === 0,
        ];
    }

    /**
     * Materiel dont l'entretien est a prevoir : entretien planifie dont la date
     * est depassee, ou panne non resolue. Aucune prediction mecanique n'est
     * faite — on ne remonte que des echeances DECLAREES par l'exploitant.
     *
     * @return list<array<string, mixed>>
     */
    public function maintenanceDue(Company $company, ?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();

        $overdue = FarmEquipmentMaintenance::query()
            ->where('company_id', $company->id)
            ->where('status', FarmMaintenanceStatus::Planned->value)
            ->whereNotNull('planned_at')
            ->where('planned_at', '<', $now)
            ->orderBy('planned_at')
            ->get();

        $rows = [];

        foreach ($overdue as $maintenance) {
            $rows[] = [
                'farm_equipment_id' => $maintenance->farm_equipment_id,
                'maintenance_id' => $maintenance->id,
                'title' => $maintenance->title,
                'maintenance_type' => $maintenance->maintenance_type,
                'planned_at' => $maintenance->planned_at?->toIso8601String(),
                'reason' => 'overdue_maintenance',
            ];
        }

        $unresolved = FarmEquipmentBreakdown::query()
            ->where('company_id', $company->id)
            ->whereNull('resolved_at')
            ->orderBy('occurred_at')
            ->get();

        foreach ($unresolved as $breakdown) {
            $rows[] = [
                'farm_equipment_id' => $breakdown->farm_equipment_id,
                'breakdown_id' => $breakdown->id,
                'title' => $breakdown->title,
                'severity' => $breakdown->severity,
                'occurred_at' => $breakdown->occurred_at->toIso8601String(),
                'reason' => 'unresolved_breakdown',
            ];
        }

        return $rows;
    }

    /**
     * Un materiel sans compteur ne peut pas recevoir de releve : on refuse
     * explicitement plutot que d'ecrire une valeur sans signification.
     */
    public function assertMeterTypeMatches(FarmEquipment $equipment, FarmEquipmentMeterType $type): void
    {
        if ($equipment->meterTypeEnum() !== $type) {
            throw FarmEquipmentException::meterTypeMismatch($equipment->meter_type, $type->value);
        }
    }

    private function assertBelongsToCompany(Company $company, FarmEquipment $equipment): void
    {
        if ($equipment->company_id !== null && $equipment->company_id !== $company->id) {
            throw FarmEquipmentException::equipmentNotInCompany($equipment->id);
        }
    }

    /**
     * @return numeric-string
     */
    private function assertNotNegative(string $value): string
    {
        if (is_numeric($value) === false || (float) $value < 0) {
            throw FarmEquipmentException::invalidCost($value);
        }

        return number_format((float) $value, 2, '.', '');
    }
}
