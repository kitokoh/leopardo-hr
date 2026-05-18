<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function registerToken(Employee $employee, string $token, string $platform = 'android', ?string $deviceName = null): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'token' => $token,
            ],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    public function removeToken(Employee $employee, string $token): bool
    {
        return DeviceToken::query()
            ->where('employee_id', $employee->id)
            ->where('token', $token)
            ->delete() > 0;
    }

    public function sendToEmployee(Employee $employee, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToCompany(string $companyId, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::query()
            ->whereHas('employee', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $serverKey = config('services.firebase.server_key');

        if (empty($serverKey)) {
            Log::warning('Firebase server key not configured, skipping push notification');

            return 0;
        }

        $sent = 0;
        $chunks = array_chunk($tokens, 500);

        foreach ($chunks as $chunk) {
            try {
                $payload = [
                    'registration_ids' => $chunk,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $data,
                    'priority' => 'high',
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', $payload);

                if ($response->successful()) {
                    $result = $response->json();
                    $sent += (int) ($result['success'] ?? 0);

                    $this->handleFailedTokens($chunk, $result['results'] ?? []);
                }
            } catch (\Throwable $e) {
                Log::error('Push notification failed', [
                    'error' => $e->getMessage(),
                    'token_count' => count($chunk),
                ]);
            }
        }

        return $sent;
    }

    private function handleFailedTokens(array $tokens, array $results): void
    {
        foreach ($results as $index => $result) {
            if (isset($result['error']) && in_array($result['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                DeviceToken::query()
                    ->where('token', $tokens[$index] ?? '')
                    ->update(['is_active' => false]);
            }
        }
    }
}
