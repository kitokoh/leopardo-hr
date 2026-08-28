<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingAlreadyReviewedException;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingFutureException;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingRejectedException;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relevés de compteur FuelStation — FUEL-004 (spec §13).
 *
 * Garanties :
 *  - append-only : une correction crée une nouvelle version (jamais de
 *    suppression), exige un motif et trace l'audit ;
 *  - idempotence : UNIQUE(company_id, idempotency_key) — rejeu = relecture ;
 *  - zéro fuite tenant : station/pompe/compteur résolus dans le tenant
 *    courant (scopes + FK composites) ; opérateur vérifié dans le tenant ;
 *  - valeur décroissante → intervalle `anomaly` (jamais de delta négatif
 *    silencieux), sauf rollover documenté sur le compteur ;
 *  - zéro flottant métier : valeurs en unités mineures entières.
 */
final class MeterReadingService
{
    /** Dérive d'horloge maximale acceptée pour un relevé « maintenant ». */
    private const MAX_CLOCK_DRIFT_SECONDS = 300;

    /**
     * Enregistre un relevé cumulé pour (station, pompe, compteur).
     *
     * @param  array{
     *     reading_value_minor: int,
     *     reading_unit?: string,
     *     captured_at?: string|null,
     *     timezone?: string,
     *     shift_id?: int|null,
     *     device_reference?: string|null,
     *     idempotency_key: string,
     * }  $input
     * @return array<string, mixed>
     */
    public function record(
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
        array $input,
        Employee $actor,
    ): array {
        $companyId = (string) $station->getAttribute('company_id');
        $key = (string) $input['idempotency_key'];

        // 1. Rejeu idempotent.
        $existing = FuelMeterReading::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return $this->readingPayload($existing, replayed: true);
        }

        // 2. Cohérence des références (toutes résolues dans le tenant).
        $this->assertLinked($station, $pump, $meter, $companyId);
        $this->assertOperatorInTenant($actor, $companyId);

        // 3. Horodatage.
        $capturedAtUtc = $input['captured_at'] !== null && $input['captured_at'] !== ''
            ? Carbon::parse((string) $input['captured_at'])->utc()
            : Carbon::now('UTC');

        if ($capturedAtUtc->greaterThan(Carbon::now('UTC')->addSeconds(self::MAX_CLOCK_DRIFT_SECONDS))) {
            throw new FuelReadingFutureException;
        }

        $timezone = (string) ($input['timezone'] ?? $station->getAttribute('timezone') ?? 'UTC');
        $valueMinor = (int) $input['reading_value_minor'];

        if ($valueMinor < 0) {
            throw new FuelReadingRejectedException('Valeur de relevé négative refusée.');
        }

        // 4. Relevé précédent + calcul de l'intervalle.
        $previous = FuelMeterReading::query()
            ->where('company_id', $companyId)
            ->where('meter_id', (int) $meter->getAttribute('id'))
            ->where('captured_at_utc', '<', $capturedAtUtc)
            ->where('status', '!=', FuelMeterReading::STATUS_REJECTED)
            ->orderByDesc('captured_at_utc')
            ->first();

        $intervalPlan = $this->buildIntervalPlan($meter, $previous, $valueMinor, $capturedAtUtc);

        // 5. Persistance (transaction).
        return DB::transaction(function () use (
            $companyId,
            $station,
            $pump,
            $meter,
            $input,
            $actor,
            $capturedAtUtc,
            $timezone,
            $valueMinor,
            $previous,
            $intervalPlan,
            $key,
        ): array {
            /** @var FuelMeterReading $reading */
            $reading = FuelMeterReading::query()->create([
                'company_id' => $companyId,
                'station_id' => (int) $station->getAttribute('id'),
                'pump_id' => (int) $pump->getAttribute('id'),
                'meter_id' => (int) $meter->getAttribute('id'),
                'reading_value_minor' => $valueMinor,
                'reading_unit' => (string) ($input['reading_unit'] ?? 'l'),
                'captured_at_utc' => $capturedAtUtc,
                'captured_at_station_local' => $capturedAtUtc->copy()->setTimezone($timezone),
                'timezone' => $timezone,
                'captured_by_employee_id' => (int) $actor->getAttribute('id'),
                'shift_id' => $input['shift_id'] ?? null,
                'source_code' => FuelMeterReading::SOURCE_OPERATOR,
                'device_reference' => $input['device_reference'] ?? null,
                'idempotency_key' => $key,
                'status' => $intervalPlan['status'] === 'valid' || $intervalPlan['status'] === 'rollover'
                    ? FuelMeterReading::STATUS_ACCEPTED
                    : FuelMeterReading::STATUS_SUBMITTED,
            ]);

            $interval = null;

            if ($previous !== null) {
                /** @var FuelMeterInterval $interval */
                $interval = FuelMeterInterval::query()->create([
                    'company_id' => $companyId,
                    'meter_id' => (int) $meter->getAttribute('id'),
                    'previous_reading_id' => (int) $previous->getAttribute('id'),
                    'current_reading_id' => (int) $reading->getAttribute('id'),
                    'previous_value_minor' => $intervalPlan['previous_value_minor'],
                    'current_value_minor' => $valueMinor,
                    'delta_minor' => $intervalPlan['delta_minor'],
                    'interval_seconds' => $intervalPlan['interval_seconds'],
                    'calculated_at' => Carbon::now(),
                    'calculation_status' => $intervalPlan['status'],
                ]);
            }

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => (int) $actor->getAttribute('id'),
                'action' => 'fuel.reading.recorded',
                'auditable_type' => FuelMeterReading::class,
                'auditable_id' => (int) $reading->getAttribute('id'),
                'old_values' => [],
                'new_values' => [
                    'station_id' => (int) $station->getAttribute('id'),
                    'pump_id' => (int) $pump->getAttribute('id'),
                    'meter_id' => (int) $meter->getAttribute('id'),
                    'reading_value_minor' => $valueMinor,
                    'interval_status' => $intervalPlan['status'],
                ],
            ]);

            return $this->readingPayload($reading, false, $interval);
        });
    }

    /**
     * Correction versionnée d'un relevé : l'original est marqué
     * 'corrected', une nouvelle version (source=correction) est créée.
     *
     * @return array<string, mixed>
     */
    public function correct(FuelMeterReading $original, string $reason, int $newValueMinor, Employee $actor): array
    {
        $companyId = (string) $original->getAttribute('company_id');

        if (in_array($original->getAttribute('status'), [FuelMeterReading::STATUS_CORRECTED, FuelMeterReading::STATUS_REJECTED], true)) {
            throw new FuelReadingAlreadyReviewedException;
        }

        if ($newValueMinor < 0) {
            throw new FuelReadingRejectedException('Valeur de relevé négative refusée.');
        }

        return DB::transaction(function () use ($original, $reason, $newValueMinor, $actor, $companyId): array {
            $original->forceFill([
                'status' => FuelMeterReading::STATUS_CORRECTED,
                'correction_reason' => $reason,
            ])->save();

            /** @var FuelMeterReading $corrected */
            $corrected = FuelMeterReading::query()->create([
                'company_id' => $companyId,
                'station_id' => $original->getAttribute('station_id'),
                'pump_id' => $original->getAttribute('pump_id'),
                'meter_id' => $original->getAttribute('meter_id'),
                'reading_value_minor' => $newValueMinor,
                'reading_unit' => $original->getAttribute('reading_unit'),
                'captured_at_utc' => $original->getAttribute('captured_at_utc'),
                'captured_at_station_local' => $original->getAttribute('captured_at_station_local'),
                'timezone' => $original->getAttribute('timezone'),
                'captured_by_employee_id' => (int) $actor->getAttribute('id'),
                'shift_id' => $original->getAttribute('shift_id'),
                'source_code' => FuelMeterReading::SOURCE_CORRECTION,
                'idempotency_key' => 'corr-'.(string) $original->getAttribute('idempotency_key').'-'.uniqid('', true),
                'status' => FuelMeterReading::STATUS_ACCEPTED,
                'correction_reason' => $reason,
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => (int) $actor->getAttribute('id'),
                'action' => 'fuel.reading.corrected',
                'auditable_type' => FuelMeterReading::class,
                'auditable_id' => (int) $original->getAttribute('id'),
                'old_values' => ['reading_value_minor' => $original->getAttribute('reading_value_minor')],
                'new_values' => ['reading_value_minor' => $newValueMinor, 'reason' => $reason],
            ]);

            return $this->readingPayload($corrected, false);
        });
    }

    /**
     * Revue d'un intervalle en anomalie : `accept` → valid (ou rollover),
     * `reject` → anomalie finale. Audit obligatoire.
     *
     * @return array<string, mixed>
     */
    public function review(FuelMeterInterval $interval, string $decision, ?string $note, Employee $actor): array
    {
        if ($interval->getAttribute('calculation_status') !== FuelMeterInterval::STATUS_PENDING_REVIEW
            && $interval->getAttribute('calculation_status') !== FuelMeterInterval::STATUS_ANOMALY) {
            throw new FuelReadingAlreadyReviewedException;
        }

        $status = $decision === 'accept' ? FuelMeterInterval::STATUS_VALID : FuelMeterInterval::STATUS_ANOMALY;

        $interval->forceFill([
            'calculation_status' => $status,
        ])->save();

        AuditLog::create([
            'company_id' => (string) $interval->getAttribute('company_id'),
            'user_id' => (int) $actor->getAttribute('id'),
            'action' => 'fuel.interval.reviewed',
            'auditable_type' => FuelMeterInterval::class,
            'auditable_id' => (int) $interval->getAttribute('id'),
            'old_values' => ['calculation_status' => FuelMeterInterval::STATUS_PENDING_REVIEW],
            'new_values' => ['calculation_status' => $status, 'note' => $note],
        ]);

        return [
            'interval_id' => (int) $interval->getAttribute('id'),
            'calculation_status' => $status,
            'reviewed_by' => (int) $actor->getAttribute('id'),
        ];
    }

    /**
     * @return array{previous_value_minor: int, delta_minor: int, interval_seconds: int, status: string}
     */
    private function buildIntervalPlan(
        FuelMeterRegister $meter,
        ?FuelMeterReading $previous,
        int $currentValueMinor,
        Carbon $capturedAtUtc,
    ): array {
        if ($previous === null) {
            return [
                'previous_value_minor' => 0,
                'delta_minor' => 0,
                'interval_seconds' => 0,
                'status' => FuelMeterInterval::STATUS_VALID,
            ];
        }

        $previousValue = (int) $previous->getAttribute('reading_value_minor');
        $previousAt = Carbon::parse((string) $previous->getAttribute('captured_at_utc'));
        $intervalSeconds = max(0, $capturedAtUtc->diffInSeconds($previousAt));

        if ($currentValueMinor >= $previousValue) {
            return [
                'previous_value_minor' => $previousValue,
                'delta_minor' => $currentValueMinor - $previousValue,
                'interval_seconds' => $intervalSeconds,
                'status' => FuelMeterInterval::STATUS_VALID,
            ];
        }

        // Valeur décroissante : rollover documenté ? Sinon anomalie.
        $rolloverLimit = $meter->getAttribute('rollover_limit');

        if ($rolloverLimit !== null && (int) $rolloverLimit > 0 && $currentValueMinor <= (int) $rolloverLimit) {
            $delta = ((int) $rolloverLimit - $previousValue) + $currentValueMinor;

            return [
                'previous_value_minor' => $previousValue,
                'delta_minor' => $delta,
                'interval_seconds' => $intervalSeconds,
                'status' => FuelMeterInterval::STATUS_ROLLOVER,
            ];
        }

        return [
            'previous_value_minor' => $previousValue,
            'delta_minor' => $currentValueMinor - $previousValue,
            'interval_seconds' => $intervalSeconds,
            'status' => FuelMeterInterval::STATUS_PENDING_REVIEW,
        ];
    }

    private function assertLinked(FuelStation $station, FuelPump $pump, FuelMeterRegister $meter, string $companyId): void
    {
        if ((string) $station->getAttribute('company_id') !== $companyId
            || (int) $pump->getAttribute('station_id') !== (int) $station->getAttribute('id')
            || (int) $meter->getAttribute('pump_id') !== (int) $pump->getAttribute('id')
            || (string) $meter->getAttribute('company_id') !== $companyId) {
            throw new FuelReadingRejectedException('Références incohérentes (station/pompe/compteur).');
        }
    }

    private function assertOperatorInTenant(Employee $actor, string $companyId): void
    {
        if ((string) $actor->getAttribute('company_id') !== $companyId) {
            throw new FuelReadingRejectedException('Opérateur hors tenant.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readingPayload(FuelMeterReading $reading, bool $replayed, ?FuelMeterInterval $interval = null): array
    {
        return [
            'reading' => [
                'id' => (int) $reading->getAttribute('id'),
                'meter_id' => (int) $reading->getAttribute('meter_id'),
                'reading_value_minor' => (int) $reading->getAttribute('reading_value_minor'),
                'reading_unit' => $reading->getAttribute('reading_unit'),
                'captured_at_utc' => Carbon::parse((string) $reading->getAttribute('captured_at_utc'))->toIso8601String(),
                'status' => $reading->getAttribute('status'),
                'source_code' => $reading->getAttribute('source_code'),
            ],
            'interval' => $interval !== null ? [
                'id' => (int) $interval->getAttribute('id'),
                'delta_minor' => (int) $interval->getAttribute('delta_minor'),
                'calculation_status' => $interval->getAttribute('calculation_status'),
                'interval_seconds' => (int) $interval->getAttribute('interval_seconds'),
            ] : null,
            'replayed' => $replayed,
            'correlation_id' => correlation_id(),
        ];
    }
}
