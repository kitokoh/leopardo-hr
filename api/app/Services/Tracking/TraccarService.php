<?php

namespace App\Services\Tracking;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class TraccarService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        /** @var string $url */
        $url = config('tracking.traccar_url', 'http://localhost:8082');
        $this->baseUrl = rtrim($url, '/');

        /** @var string $token */
        $token = config('tracking.traccar_token', '');
        $this->token = $token;
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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

        return count($positions) > 0 ? $positions[0] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->get("{$this->baseUrl}{$path}", $params);

        if ($response->failed()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $path, array $data): array
    {
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post("{$this->baseUrl}{$path}", $data);

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function put(string $path, array $data): array
    {
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->put("{$this->baseUrl}{$path}", $data);

        return $response->json() ?? [];
    }

    private function delete(string $path): void
    {
        Http::withToken($this->token)
            ->timeout(15)
            ->delete("{$this->baseUrl}{$path}");
    }
}
