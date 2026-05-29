<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
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
        $projectId = config('services.firebase.project_id');
        $accessToken = $this->getAccessToken();

        if (empty($projectId) || empty($accessToken)) {
            Log::warning('Firebase project ID or access token not available, skipping push notification');
            return 0;
        }

        $sent = 0;
        $failedTokens = [];

        foreach ($tokens as $token) {
            try {
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $this->formatDataForFcm($data),
                    ],
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

                if ($response->successful()) {
                    $sent++;
                } else {
                    $error = $response->json('error.status');
                    if (in_array($error, ['NOT_FOUND', 'UNAUTHENTICATED', 'INVALID_ARGUMENT'])) {
                        $failedTokens[] = $token;
                    }
                    Log::warning('Firebase HTTP v1 error', ['response' => $response->json()]);
                }
            } catch (\Throwable $e) {
                Log::error('Push notification failed', [
                    'error' => $e->getMessage(),
                    'token' => $token,
                ]);
            }
        }

        if (!empty($failedTokens)) {
            $this->handleFailedTokens($failedTokens);
        }

        return $sent;
    }

    private function getAccessToken(): ?string
    {
        $cacheKey = 'firebase_access_token';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $credentialsJson = config('services.firebase.credentials');
        if (empty($credentialsJson)) {
            return null;
        }

        if (is_file($credentialsJson) && file_exists($credentialsJson)) {
            $credentialsJson = file_get_contents($credentialsJson);
        }

        $credentials = json_decode($credentialsJson, true);
        if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
            return null;
        }

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = '';
        openssl_sign($base64UrlHeader . '.' . $base64UrlPayload, $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            $token = $response->json('access_token');
            Cache::put($cacheKey, $token, 3000);
            return $token;
        }

        Log::error('Failed to get Firebase access token', ['response' => $response->json()]);

        return null;
    }

    private function formatDataForFcm(array $data): array
    {
        $formatted = [];
        foreach ($data as $key => $value) {
            $formatted[(string) $key] = is_array($value) ? json_encode($value) : (string) $value;
        }
        return $formatted;
    }

    private function handleFailedTokens(array $tokens): void
    {
        if (empty($tokens)) {
            return;
        }
        DeviceToken::query()
            ->whereIn('token', $tokens)
            ->update(['is_active' => false]);
    }
}
