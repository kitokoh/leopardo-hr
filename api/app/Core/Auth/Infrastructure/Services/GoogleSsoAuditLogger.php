<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use Illuminate\Support\Facades\Log;

/**
 * #7962 — journal d'audit des flux Google SSO, isolé de l'Application.
 *
 * Les Actions ne peuvent pas importer `Illuminate\Support\Facades\Log`
 * (`check-layer-purity.sh`), et c'est une bonne contrainte : un cas d'usage ne
 * doit pas décider du canal de journalisation. Ce service porte les trois
 * traces que le contrôleur écrivait en ligne, avec les mêmes clés — les
 * requêtes d'exploitation existantes continuent de fonctionner.
 */
final class GoogleSsoAuditLogger
{
    public function linked(string $employeeId, string $email): void
    {
        Log::channel('audit')->info('auth.google.linked', [
            'employee_id' => $employeeId,
            'email' => $email,
        ]);
    }

    public function identityMismatch(string $employeeId, string $email): void
    {
        Log::channel('audit')->warning('auth.google.identity_mismatch', [
            'employee_id' => $employeeId,
            'email' => $email,
        ]);
    }

    public function signupIntent(string $email, ?string $plan): void
    {
        Log::channel('audit')->info('auth.google.signup_intent', [
            'email' => $email,
            'plan' => $plan,
        ]);
    }

    public function emailNotVerified(string $email, string $flow): void
    {
        Log::warning('auth.google.'.$flow.'_email_not_verified', ['email' => $email]);
    }

    public function exchangeFailed(string $flow, string $message): void
    {
        Log::error('auth.google.'.$flow.'_failed', ['error' => $message]);
    }

    public function notConfigured(): void
    {
        Log::error('auth.google.not_configured', [
            'client_id' => filled(config('services.google.client_id')),
            'client_secret' => filled(config('services.google.client_secret')),
            'redirect' => filled(config('services.google.redirect')),
        ]);
    }
}
