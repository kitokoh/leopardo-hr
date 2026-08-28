<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Enums\FuelReadingAnomalyReason;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingException;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Enregistrement et correction des relevés de compteur — Issue #5798 (FUEL-004).
 *
 * Règles :
 *   - idempotence : un doublon (company_id, meter_id, read_at) est détecté et
 *     renvoyé tel quel (zéro doublon, pas d'erreur 500) ;
 *   - delta : différence avec le relevé effectif précédent du même compteur ;
 *   - anomalie : valeur décroissante → `decreasing_value` (sauf rollover
 *     explicite `is_rollover`, ex. remplacement du compteur) ;
 *   - correction versionnée : `correct()` crée une NOUVELLE ligne liée via
 *     `corrects_reading_id` (append-only, auditée par Auditable) ;
 *   - isolation tenant : portée par BelongsToCompany (fail-closed #3727).
 */
class FuelMeterReadingService
{
    /**
     * Enregistre un relevé avec calcul du delta et détection d'anomalie.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): FuelMeterReading
    {
        $meterId = (int) ($payload['meter_id'] ?? 0);
        $readAt = $payload['read_at'] ?? now();

        // Idempotence : le doublon (compteur, instant) est renvoyé tel quel.
        $existing = FuelMeterReading::query()
            ->where('meter_id', $meterId)
            ->where('read_at', $readAt instanceof Carbon ? $readAt : Carbon::parse($readAt))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $reading = new FuelMeterReading([
            'meter_id' => $meterId,
            'pump_id' => $payload['pump_id'] ?? null,
            'site_id' => $payload['site_id'] ?? null,
            'station_id' => $payload['station_id'] ?? null,
            'operator_id' => $payload['operator_id'] ?? null,
            'shift_ref' => $payload['shift_ref'] ?? null,
            'reading_value' => $payload['reading_value'],
            'read_at' => $readAt,
            'read_at_local' => $payload['read_at_local'] ?? null,
            'is_rollover' => (bool) ($payload['is_rollover'] ?? false),
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $this->computeDeltaAndAnomaly($reading);

        try {
            $reading->save();
        } catch (QueryException $exception) {
            // Course (duplicate key) : renvoyer l'existant, zéro doublon.
            if (str_contains($exception->getMessage(), 'fuel_meter_readings_dup_unique')) {
                $existing = FuelMeterReading::query()
                    ->where('meter_id', $meterId)
                    ->where('read_at', $reading->read_at)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }

        return $reading;
    }

    /**
     * Correction versionnée : crée une NOUVELLE ligne liée au relevé corrigé.
     *
     * @param  array<string, mixed>  $payload
     */
    public function correct(int $originalReadingId, array $payload): FuelMeterReading
    {
        $original = FuelMeterReading::query()->find($originalReadingId);

        if ($original === null) {
            throw new FuelReadingException('Relevé d\'origine introuvable.');
        }

        if ($original->corrects_reading_id !== null) {
            throw new FuelReadingException('Un relevé corrigé ne peut pas être re-corrigé directement.');
        }

        $correction = new FuelMeterReading([
            'company_id' => $original->company_id,
            'meter_id' => $original->meter_id,
            'pump_id' => $original->pump_id,
            'site_id' => $original->site_id,
            'station_id' => $original->station_id,
            'operator_id' => $original->operator_id,
            'shift_ref' => $original->shift_ref,
            'reading_value' => $payload['reading_value'],
            'read_at' => $original->read_at,
            'read_at_local' => $original->read_at_local,
            'delta' => $payload['delta'] ?? $original->delta,
            'is_rollover' => $original->is_rollover,
            'is_anomaly' => false,
            'corrects_reading_id' => $original->id,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $correction->save();

        return $correction;
    }

    /**
     * Calcule le delta avec le relevé effectif précédent et marque l'anomalie
     * en cas de décroissance (hors rollover explicite).
     */
    private function computeDeltaAndAnomaly(FuelMeterReading $reading): void
    {
        $previous = FuelMeterReading::query()
            ->where('meter_id', $reading->meter_id)
            ->where('read_at', '<', $reading->read_at)
            ->whereNull('corrects_reading_id')
            ->orderByDesc('read_at')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            $reading->delta = null;
            $reading->is_anomaly = false;
            $reading->anomaly_reason = null;

            return;
        }

        $delta = (float) $reading->reading_value - (float) $previous->reading_value;

        if ($delta < 0 && ! $reading->is_rollover) {
            $reading->delta = $delta;
            $reading->is_anomaly = true;
            $reading->anomaly_reason = FuelReadingAnomalyReason::DecreasingValue->value;

            return;
        }

        // Rollover (compteur remplacé / cycle neuf) : le delta repart du relevé.
        $reading->delta = $reading->is_rollover ? (float) $reading->reading_value : $delta;
        $reading->is_anomaly = false;
        $reading->anomaly_reason = null;
    }
}
