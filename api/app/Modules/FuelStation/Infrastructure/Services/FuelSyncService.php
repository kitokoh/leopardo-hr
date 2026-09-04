<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Facades\Schema;

/**
 * Offline & synchronisation des relevés — FUEL-014 (issue #5808).
 *
 * - `outboxForDevice` : éléments récents (relevés, ventes) postérieurs à
 *   `since_id`, bornés — le terminal pompiste se resynchronise après
 *   offline sans rejouer tout l'historique.
 * - `bulkReadings` / `bulkSales` : réception idempotente (idempotency_key /
 *   external_id) — un rejeu réseau ne crée jamais de doublon. Chaque lot
 *   reçu est journalisé dans l'outbox (fuel.sync.readings.received.v1 /
 *   fuel.sync.sales.received.v1) pour audit et reprise.
 */
final class FuelSyncService
{
    public function __construct(
        private readonly FuelOutboxPublisher $outbox,
        private readonly FuelSaleService $sales,
        private readonly MeterReadingService $readings,
    ) {}

    /**
     * @return array{readings: array<int, array<string, mixed>>, sales: array<int, array<string, mixed>>}
     */
    public function outboxForDevice(string $companyId, int $sinceId, int $limit): array
    {
        $readings = [];

        if (Schema::hasTable('fuel_meter_readings')) {
            $readings = FuelMeterReading::query()
                ->where('company_id', $companyId)
                ->where('id', '>', $sinceId)
                ->orderBy('id')
                ->limit(min(500, max(1, $limit)))
                ->get()
                ->map(fn (FuelMeterReading $r) => [
                    'id' => $r->id,
                    'meter_id' => $r->meter_id,
                    'reading_value_minor' => $r->reading_value_minor,
                    'captured_at_utc' => $r->captured_at_utc->toISOString(),
                    'status' => $r->status,
                    'idempotency_key' => $r->idempotency_key,
                ])
                ->values()
                ->all();
        }

        $sales = FuelSale::query()
            ->where('company_id', $companyId)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit(min(500, max(1, $limit)))
            ->get()
            ->map(fn (FuelSale $s) => [
                'id' => $s->id,
                'product' => $s->product,
                'quantity' => $s->quantity,
                'amount' => $s->amount,
                'sale_time' => $s->sale_time->toISOString(),
                'external_id' => $s->external_id,
            ])
            ->values()
            ->all();

        return ['readings' => $readings, 'sales' => $sales];
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return array{received: int, duplicates: int}
     */
    public function bulkReadings(Employee $actor, array $payloads): array
    {
        $received = 0;
        $duplicates = 0;

        foreach ($payloads as $payload) {
            $key = isset($payload['idempotency_key']) && is_string($payload['idempotency_key'])
                ? $payload['idempotency_key']
                : null;

            if ($key !== null) {
                $exists = FuelMeterReading::query()
                    ->where('company_id', (string) $actor->company_id)
                    ->where('idempotency_key', $key)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }
            }

            // Résolution tenant-scoped (station/pompe/compteur) puis délégation
            // au service canonique (append-only, delta/anomalie, idempotence).
            $station = FuelStation::query()
                ->where('company_id', (string) $actor->company_id)
                ->find((int) ($payload['station_id'] ?? 0));

            $pump = FuelPump::query()
                ->where('company_id', (string) $actor->company_id)
                ->find((int) ($payload['pump_id'] ?? 0));

            $meter = FuelMeterRegister::query()
                ->where('company_id', (string) $actor->company_id)
                ->find((int) ($payload['meter_id'] ?? 0));

            if (! $station instanceof FuelStation || ! $pump instanceof FuelPump || ! $meter instanceof FuelMeterRegister) {
                continue;
            }

            $this->readings->record($station, $pump, $meter, $this->readingInput($payload), $actor);
            $received++;
        }

        $this->outbox->publish(
            (string) $actor->company_id,
            FuelOutboxEvent::EVENT_SYNC_READINGS_RECEIVED,
            ['received' => $received, 'duplicates' => $duplicates],
            'fuel_sync',
            'readings-'.now()->timestamp,
        );

        return ['received' => $received, 'duplicates' => $duplicates];
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return array{received: int, duplicates: int}
     */
    public function bulkSales(Employee $actor, array $payloads): array
    {
        $received = 0;
        $duplicates = 0;

        foreach ($payloads as $payload) {
            $externalId = isset($payload['external_id']) && is_string($payload['external_id'])
                ? $payload['external_id']
                : null;

            if ($externalId !== null) {
                $exists = FuelSale::query()
                    ->where('company_id', (string) $actor->company_id)
                    ->where('external_id', $externalId)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }
            }

            $this->sales->record($actor, $payload);
            $received++;
        }

        $this->outbox->publish(
            (string) $actor->company_id,
            FuelOutboxEvent::EVENT_SYNC_SALES_RECEIVED,
            ['received' => $received, 'duplicates' => $duplicates],
            'fuel_sync',
            'sales-'.now()->timestamp,
        );

        return ['received' => $received, 'duplicates' => $duplicates];
    }

    /**
     * Construit l'entrée attendue par MeterReadingService::record() depuis
     * un payload de synchronisation (clés bornées, jamais de données brutes).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     reading_value_minor: int,
     *     reading_unit?: string,
     *     captured_at?: string|null,
     *     timezone?: string,
     *     shift_id?: int|null,
     *     device_reference?: string|null,
     *     idempotency_key: string,
     * }
     */
    private function readingInput(array $payload): array
    {
        $input = [
            'reading_value_minor' => (int) ($payload['reading_value_minor'] ?? 0),
            'idempotency_key' => is_string($payload['idempotency_key'] ?? null) ? $payload['idempotency_key'] : '',
        ];

        if (isset($payload['reading_unit']) && is_string($payload['reading_unit'])) {
            $input['reading_unit'] = $payload['reading_unit'];
        }

        if (isset($payload['captured_at'])) {
            $input['captured_at'] = is_string($payload['captured_at']) ? $payload['captured_at'] : null;
        }

        if (isset($payload['timezone']) && is_string($payload['timezone'])) {
            $input['timezone'] = $payload['timezone'];
        }

        if (isset($payload['shift_id'])) {
            $input['shift_id'] = (int) $payload['shift_id'];
        }

        if (isset($payload['device_reference']) && is_string($payload['device_reference'])) {
            $input['device_reference'] = $payload['device_reference'];
        }

        return $input;
    }
}
