<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcDriverPosition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Ingestion IDEMPOTENTE des positions chauffeurs (BC-34 VTC, VTC-05/#8361).
 *
 * Contrainte UNIQUE(company_id, driver_id, recorded_at) : un rejeu réseau de
 * la même position retourne l'existante (200), jamais de doublon. La
 * dernière position connue du chauffeur (`current_*`, base du dispatch
 * VTC-04) n'avance JAMAIS en arrière : une position hors-ordre est
 * historisée mais ne régresse pas `location_updated_at`.
 *
 * Donnée personnelle (RGPD) : rétention `vtc.positions_retention_days`,
 * purge `vtc:purge-positions` (VTC-06).
 */
final class RecordVtcDriverPositionAction
{
    /**
     * @return array{0: VtcDriverPosition, 1: bool} [position, true si rejeu idempotent]
     */
    public function execute(VtcDriver $driver, float $latitude, float $longitude, Carbon $recordedAt, string $source = 'app'): array
    {
        $existing = VtcDriverPosition::query()
            ->where('driver_id', $driver->id)
            ->where('recorded_at', $recordedAt)
            ->first();

        if ($existing instanceof VtcDriverPosition) {
            return [$existing, true];
        }

        try {
            /** @var VtcDriverPosition $position */
            $position = VtcDriverPosition::query()->create([
                'driver_id' => $driver->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => $recordedAt,
                'source' => $source,
            ]);
        } catch (QueryException) {
            // Doublon sous concurrence : le rejeu gagne (idempotence).
            /** @var VtcDriverPosition $existing */
            $existing = VtcDriverPosition::query()
                ->where('driver_id', $driver->id)
                ->where('recorded_at', $recordedAt)
                ->firstOrFail();

            return [$existing, true];
        }

        // Dernière position connue : monotone — jamais de régression sur une
        // position plus ancienne que celle déjà connue.
        $lastKnownAt = $driver->location_updated_at;

        if ($lastKnownAt === null || $recordedAt->greaterThan($lastKnownAt)) {
            $driver->forceFill([
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
                'location_updated_at' => $recordedAt,
            ])->save();
        }

        return [$position, false];
    }
}
