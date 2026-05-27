<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class OnboardingQrService
{
    private const TOKEN_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function employeeProfilePayload(Employee $employee): array
    {
        $payload = [
            'v' => self::TOKEN_VERSION,
            'type' => 'employee_profile',
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'preferred_name' => $employee->preferred_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'personal_email' => $employee->personal_email,
                'personal_phone' => $employee->personal_phone,
            ],
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ];

        return [
            'token' => $this->sign($payload),
            'type' => 'employee_profile',
            'expires_at' => $payload['expires_at'],
            'profile' => $payload['employee'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function companyOnboardingPayload(Company $company, Employee $actor): array
    {
        $payload = [
            'v' => self::TOKEN_VERSION,
            'type' => 'company_onboarding',
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'sector' => $company->sector,
                'country' => $company->country,
                'city' => $company->city,
                'email' => $company->email,
            ],
            'issued_by' => [
                'id' => $actor->id,
                'name' => trim($actor->first_name.' '.$actor->last_name),
                'email' => $actor->email,
            ],
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ];

        return [
            'token' => $this->sign($payload),
            'type' => 'company_onboarding',
            'expires_at' => $payload['expires_at'],
            'company' => $payload['company'],
            'issued_by' => $payload['issued_by'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeEmployeeProfile(string $token): array
    {
        return $this->verify($token, 'employee_profile');
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeCompanyOnboarding(string $token): array
    {
        return $this->verify($token, 'company_onboarding');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey());

        return $encodedPayload.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    private function verify(string $token, string $expectedType): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ValidationException::withMessages(['qr_token' => 'QR token invalide.']);
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->signingKey());
        if (! hash_equals($expectedSignature, $signature)) {
            throw ValidationException::withMessages(['qr_token' => 'Signature QR invalide.']);
        }

        $json = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['qr_token' => 'Contenu QR illisible.']);
        }

        if (($payload['type'] ?? null) !== $expectedType || ($payload['v'] ?? null) !== self::TOKEN_VERSION) {
            throw ValidationException::withMessages(['qr_token' => 'Type de QR incompatible.']);
        }

        $expiresAt = Carbon::parse((string) ($payload['expires_at'] ?? 'now'));
        if ($expiresAt->isPast()) {
            throw ValidationException::withMessages(['qr_token' => 'QR expire.']);
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function signingKey(): string
    {
        $key = (string) Config::get('app.key', '');

        return $key !== '' ? $key : 'leopardo-local-onboarding-key';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad($value, strlen($value) + (4 - strlen($value) % 4) % 4, '=');

        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw ValidationException::withMessages(['qr_token' => 'QR token invalide.']);
        }

        return $decoded;
    }
}
