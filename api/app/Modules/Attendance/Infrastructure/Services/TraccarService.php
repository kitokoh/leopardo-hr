<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class TraccarService
{
    private string $baseUrl = '';

    private string $token = '';

    public function __construct()
    {
        // QA pass 2026-08-14 (#2175) : quand TRACCAR_API_TOKEN n'est pas
        // configuré, config('tracking.traccar_token') vaut null — l'affectation
        // à une propriété `string` typée levait un TypeError → 500 sur
        // /fleet/live-map et /vehicles/{id}/position|trips. Le tracking est
        // optionnel (fail-open : données vides quand non configuré).
        $url = config('tracking.traccar_url', 'http://localhost:8082');
        $token = config('tracking.traccar_token', '');
        $this->baseUrl = rtrim(is_string($url) ? $url : '', '/');
        $this->token = is_string($token) ? $token : '';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getDevices(): array
    {
        return $this->get('/api/devices');
    }

    /**
     * @return array<string, mixed>
     */
    public function createDevice(string $name, string $uniqueId): array
    {
        return $this->post('/api/devices', [
            'name' => $name,
            'uniqueId' => $uniqueId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateDevice(int $deviceId, array $data): array
    {
        return $this->put("/api/devices/{$deviceId}", $data);
    }

    public function deleteDevice(int $deviceId): void
    {
        $this->delete("/api/devices/{$deviceId}");
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getPositions(int $deviceId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $params = ['deviceId' => $deviceId];

        if ($from) {
            $params['from'] = $from->toIso8601String();
        }
        if ($to) {
            $params['to'] = $to->toIso8601String();
        }

        return $this->get('/api/positions', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastPosition(int $deviceId): ?array
    {
        $positions = $this->get('/api/positions', ['deviceId' => $deviceId]);

        /** @var array<string, mixed>|null $position */
        $position = count($positions) > 0 ? $positions[0] : null;

        return $position;
    }

    /**
     * Dernière position de plusieurs appareils en UN appel (deviceId
     * accepte une liste séparée par des virgules) — évite le N+1 HTTP
     * de FleetController::liveMap (issue #3148).
     *
     * @param  list<int>  $deviceIds
     * @return array<int, array<string, mixed>|null> deviceId => position|null
     */
    public function getLastPositions(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_map('intval', $deviceIds)));
        $deviceIds = array_filter($deviceIds, fn (int $id): bool => $id > 0);

        if ($deviceIds === []) {
            return [];
        }

        $positions = $this->get('/api/positions', ['deviceId' => implode(',', $deviceIds)]);

        $byDevice = [];
        foreach ($positions as $position) {
            if (! is_array($position)) {
                continue;
            }
            /** @var array<string, mixed> $position */
            /** @var int $deviceIdRaw */
            $deviceIdRaw = $position['deviceId'] ?? 0;
            $deviceId = (int) $deviceIdRaw;
            if ($deviceId > 0 && ! isset($byDevice[$deviceId])) {
                $byDevice[$deviceId] = $position;
            }
        }

        $result = [];
        foreach ($deviceIds as $deviceId) {
            $result[$deviceId] = $byDevice[$deviceId] ?? null;
        }

        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getTrips(int $deviceId, Carbon $from, Carbon $to): array
    {
        return $this->get('/api/reports/trips', [
            'deviceId' => $deviceId,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
        ]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getGeofences(): array
    {
        return $this->get('/api/geofences');
    }

    /**
     * @return array<string, mixed>
     */
    public function createGeofence(string $name, string $area): array
    {
        return $this->post('/api/geofences', [
            'name' => $name,
            'area' => $area,
        ]);
    }

    public function linkGeofenceToDevice(int $geofenceId, int $deviceId): void
    {
        $this->post('/api/permissions', [
            'deviceId' => $deviceId,
            'geofenceId' => $geofenceId,
        ]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getEvents(int $deviceId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $params = ['deviceId' => $deviceId];

        if ($from) {
            $params['from'] = $from->toIso8601String();
        }
        if ($to) {
            $params['to'] = $to->toIso8601String();
        }

        return $this->get('/api/reports/events', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int|string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        if ($this->token === '') {
            return [];
        }

        $response = Http::withToken($this->token)
            ->timeout(15)
            ->get("{$this->baseUrl}{$path}", $params);

        if ($response->failed()) {
            return [];
        }

        /** @var array<int|string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $path, array $data): array
    {
        if ($this->token === '') {
            return [];
        }

        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post("{$this->baseUrl}{$path}", $data);

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function put(string $path, array $data): array
    {
        if ($this->token === '') {
            return [];
        }

        $response = Http::withToken($this->token)
            ->timeout(15)
            ->put("{$this->baseUrl}{$path}", $data);

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    private function delete(string $path): void
    {
        if ($this->token === '') {
            return;
        }

        Http::withToken($this->token)
            ->timeout(15)
            ->delete("{$this->baseUrl}{$path}");
    }
}
