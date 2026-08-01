<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Mail\TrialVerificationMail;
use App\Support\CountryDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Enregistre une demande d'essai self-service en attente de vérification OTP
 * et envoie l'email contenant le code.
 */
class RequestTrialSignup
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated): void
    {
        $email = strtolower(trim($validated['email']));

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->createPendingCompanyRequest($validated, $email, $otp);

        [$firstName, $lastName] = $this->managerNameParts($validated, $email);
        $managerName = trim($firstName.' '.$lastName);
        $country = strtoupper(trim($validated['country'] ?? 'DZ'));
        $countryDefaults = CountryDefaults::for($country);

        try {
            Mail::to($email)->send(
                new TrialVerificationMail($managerName, $otp, strtolower($countryDefaults['language']))
            );
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Failed to send OTP email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            // Allow testing in local/staging without mailer failing the request
        }
    }

    public function findExistingManager(string $email): ?Employee
    {
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO shared_tenants, public');
            }

            return Employee::query()
                ->where('email', $email)
                ->where('role', 'manager')
                ->first();
        } catch (\Throwable) {
            return null;
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO public');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string}
     */
    public function managerNameParts(array $validated, string $email): array
    {
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '') {
            [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, null);
            $nameParts = preg_split('/[._\-+]/', $localPart ?: 'manager', 2) ?: ['Manager'];
            $firstName = ucfirst($nameParts[0] ?? 'Manager');

            if ($lastName === '') {
                $lastName = isset($nameParts[1]) && trim($nameParts[1]) !== ''
                    ? ucfirst($nameParts[1])
                    : ucfirst(strtolower($domain ?: 'principal'));
            }
        }

        if ($lastName === '') {
            $lastName = 'Principal';
        }

        return [$firstName, $lastName];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function managerNameForCompanyRequest(array $validated, string $email): string
    {
        $name = trim(implode(' ', array_filter([
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        if ($name !== '') {
            return $name;
        }

        $localPart = explode('@', $email)[0] ?: 'manager';

        return str($localPart)
            ->replace(['.', '_', '-', '+'], ' ')
            ->title()
            ->toString();
    }

    private function mapRoleToSector(?string $role): string
    {
        return match ($role) {
            'founder' => 'Direction générale',
            'hr' => 'Ressources humaines',
            'operations' => 'Opérations',
            default => 'Non précisé',
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createPendingCompanyRequest(array $validated, string $email, string $otp): void
    {
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO public');
            }

            CompanyRequest::query()->create([
                'company_name' => trim($validated['company']),
                'sector' => $this->mapRoleToSector($validated['role'] ?? null),
                'country' => strtoupper(trim($validated['country'] ?? 'DZ')),
                'city' => 'Non précisé',
                'manager_name' => $this->managerNameForCompanyRequest($validated, $email),
                'manager_phone' => $validated['phone'] ?? null,
                'notes' => 'Self-service trial signup.',
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'description' => 'Self-service trial signup pending verification — source: '.($validated['source'] ?? 'direct'),
                'status' => 'pending',
                'verification_token' => $otp,
                'verification_expires_at' => now()->addMinutes(30),
                'signup_payload' => $validated,
            ]);
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Failed to create pending CompanyRequest record', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
