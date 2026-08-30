<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Carbon;

/**
 * FUEL-019 (#5813) — Détection d'anomalies FuelStation → outbox.
 *
 * - Relevé compteur anormal (valeur < dernier relevé du même compteur) ;
 * - Clôture manquante (session de caisse ouverte depuis > 24 h) ;
 * - Écart de caisse (session clôturée avec variance ≠ 0).
 *
 * Anti-spam : `idempotency_key` = `fuel-anomaly:{type}:{entity}:{jour}` —
 * une alerte par entité/période (dédup, pas de double notification).
 * Aucun secret dans les payloads (PII redacted).
 */
final class FuelAnomalyService
{
    public const MISSING_CLOSE_HOURS = 24;

    /**
     * @return array{anomalies_published: int, duplicates: int}
     */
    public function scanCompany(Company $company): array
    {
        $day = Carbon::today()->toDateString();
        $published = 0;
        $duplicates = 0;

        foreach ($this->meterAnomalies($company->id) as $reading) {
            $key = sprintf('fuel-anomaly:meter:%d:%s', $reading->id, $day);
            $result = $this->publish($company->id, $key, 'fuel.anomaly.meter.v1', [
                'reading_id' => $reading->id,
                'pump_id' => $reading->pump_id,
                'meter_id' => $reading->meter_id,
                'station_id' => $reading->station_id,
                'reading_value_minor' => $reading->reading_value_minor,
            ]);
            $result ? $published++ : $duplicates++;
        }

        foreach ($this->missingCloses($company->id) as $session) {
            $key = sprintf('fuel-anomaly:missing_close:%d:%s', $session->id, $day);
            $result = $this->publish($company->id, $key, 'fuel.anomaly.missing_close.v1', [
                'cash_session_id' => $session->id,
                'station_id' => $session->station_id,
                'opened_at' => $session->opened_at?->toIso8601String(),
            ]);
            $result ? $published++ : $duplicates++;
        }

        foreach ($this->variances($company->id) as $session) {
            $key = sprintf('fuel-anomaly:variance:%d:%s', $session->id, $day);
            $result = $this->publish($company->id, $key, 'fuel.anomaly.variance.v1', [
                'cash_session_id' => $session->id,
                'station_id' => $session->station_id,
                'variance' => $session->variance,
            ]);
            $result ? $published++ : $duplicates++;
        }

        return ['anomalies_published' => $published, 'duplicates' => $duplicates];
    }

    /**
     * Relevés dont la valeur est inférieure au dernier relevé du même compteur.
     *
     * @return \Illuminate\Support\Collection<int, FuelMeterReading>
     */
    private function meterAnomalies(string $companyId): \Illuminate\Support\Collection
    {
        $readings = FuelMeterReading::query()
            ->where('company_id', $companyId)
            ->orderByDesc('captured_at_utc')
            ->get();

        $anomalies = collect();
        $lastByMeter = [];

        foreach ($readings as $reading) {
            $meterKey = $reading->meter_id;

            if (isset($lastByMeter[$meterKey]) && (int) $reading->reading_value_minor < (int) $lastByMeter[$meterKey]) {
                $anomalies->push($reading);
            }

            $lastByMeter[$meterKey] = (int) $reading->reading_value_minor;
        }

        return $anomalies;
    }

    /**
     * @return \Illuminate\Support\Collection<int, FuelCashSession>
     */
    private function missingCloses(string $companyId): \Illuminate\Support\Collection
    {
        $cutoff = now()->subHours(self::MISSING_CLOSE_HOURS);

        return FuelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->where('opened_at', '<', $cutoff)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, FuelCashSession>
     */
    private function variances(string $companyId): \Illuminate\Support\Collection
    {
        return FuelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->where('variance', '!=', 0)
            ->get();
    }

    private function publish(string $companyId, string $idempotencyKey, string $eventType, array $payload): bool
    {
        $event = FuelOutboxEvent::query()->firstOrCreate(
            ['company_id' => $companyId, 'idempotency_key' => $idempotencyKey],
            [
                'event_type' => $eventType,
                'payload_redacted' => $payload,
                'status' => FuelOutboxEvent::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        return $event->wasRecentlyCreated;
    }
}
