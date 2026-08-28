<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Enums\FuelReadingSource;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingException;
use App\Modules\FuelStation\Domain\Models\FuelMeter;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterReadingCorrection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Relevés de compteur FuelStation (issue #5798).
 *
 * - Idempotence : unicité (company_id, meter_id, reading_at) — un rejeu du
 *   même relevé (même compteur, même horodatage) est absorbé et retourne
 *   l'existant (zéro doublon).
 * - Delta : calculé vs le relevé précédent (antérieur en `reading_at`) du
 *   même compteur ; valeur décroissante → `anomaly` (et `rollover` posé).
 * - Correction versionnée : `correct()` écrit une ligne de correction
 *   (ancienne → nouvelle valeur, motif, acteur) puis met à jour le relevé
 *   et recalcule delta/anomalie — tout est audit-trail.
 * - Zéro fuite tenant : les lectures/écritures passent par le scope
 *   BelongsToCompany (fail-closed #3727).
 */
final class FuelMeterReadingService
{
    /**
     * @param  array<string, mixed>  $attributes  pump_id, site_id, station_id,
     *                                            operator_id, shift_id, source, note, created_by
     */
    public function record(FuelMeter $meter, float $value, Carbon $readingAt, array $attributes = []): FuelMeterReading
    {
        $source = isset($attributes['source']) && is_string($attributes['source']) ? $attributes['source'] : FuelReadingSource::MANUAL;
        if (! FuelReadingSource::isValid($source)) {
            throw new FuelReadingException('Source de relevé inconnue.', 'FUEL_READING_SOURCE_INVALID');
        }

        // Idempotence : le même relevé (compteur + horodatage) ne crée rien.
        $existing = FuelMeterReading::query()
            ->where('meter_id', $meter->id)
            ->where('reading_at', $readingAt)
            ->first();

        if ($existing !== null) {
            Log::info('FuelStation: reading already exists (idempotence)', [
                'reading_id' => $existing->id,
                'meter_id' => $meter->id,
            ]);

            return $existing;
        }

        $previous = FuelMeterReading::query()
            ->where('meter_id', $meter->id)
            ->where('reading_at', '<', $readingAt)
            ->orderByDesc('reading_at')
            ->first();

        $delta = $previous !== null ? round($value - $previous->reading_value, 3) : null;
        $anomaly = $delta !== null && $delta < 0;
        $rollover = $delta !== null && $delta < 0;

        /** @var FuelMeterReading $reading */
        $reading = FuelMeterReading::query()->create([
            'meter_id' => $meter->id,
            'pump_id' => $attributes['pump_id'] ?? null,
            'site_id' => $attributes['site_id'] ?? null,
            'station_id' => $attributes['station_id'] ?? null,
            'operator_id' => $attributes['operator_id'] ?? null,
            'shift_id' => $attributes['shift_id'] ?? null,
            'reading_value' => $value,
            'reading_at' => $readingAt,
            'reading_at_local' => isset($attributes['reading_at_local']) && is_string($attributes['reading_at_local']) ? $attributes['reading_at_local'] : null,
            'delta' => $delta,
            'rollover' => $rollover,
            'anomaly' => $anomaly,
            'source' => $source,
            'note' => $attributes['note'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
        ]);

        if ($anomaly) {
            Log::warning('FuelStation: decreasing reading (anomaly)', [
                'reading_id' => $reading->id,
                'meter_id' => $meter->id,
                'value' => $value,
                'previous' => $previous?->reading_value,
            ]);
        }

        return $reading;
    }

    /**
     * Correction versionnée et auditée d'un relevé (issue #5798).
     */
    public function correct(FuelMeterReading $reading, float $newValue, string $reason, ?string $actorId): FuelMeterReading
    {
        if ($reason === '') {
            throw new FuelReadingException('Motif de correction requis.', 'FUEL_READING_CORRECTION_REASON_REQUIRED');
        }

        $oldValue = $reading->reading_value;

        FuelMeterReadingCorrection::query()->create([
            'reading_id' => $reading->id,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => substr($reason, 0, 254),
            'corrected_by' => $actorId,
        ]);

        $previous = FuelMeterReading::query()
            ->where('meter_id', $reading->meter_id)
            ->where('reading_at', '<', $reading->reading_at)
            ->orderByDesc('reading_at')
            ->first();

        $delta = $previous !== null ? round($newValue - $previous->reading_value, 3) : null;

        $reading->forceFill([
            'reading_value' => $newValue,
            'delta' => $delta,
            'rollover' => $delta !== null && $delta < 0,
            'anomaly' => $delta !== null && $delta < 0,
        ])->save();

        Log::info('FuelStation: reading corrected (versioned)', [
            'reading_id' => $reading->id,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'corrected_by' => $actorId,
        ]);

        return $reading->refresh();
    }
}
