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
    public function execute(array $validated): bool
    {
        $email = strtolower(trim($validated['email']));

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->createPendingCompanyRequest($validated, $email, $otp);

        [$firstName, $lastName] = $this->managerNameParts($validated, $email);
        $managerName = trim($firstName.' '.$lastName);
        // MULTI-PAYS (#1867) : le pays est validé en amont (obligatoire +
        // supporté) — aucun fallback silencieux vers DZ.
        $country = strtoupper(trim((string) ($validated['country'] ?? '')));
        $countryDefaults = CountryDefaults::find($country) ?? throw new \InvalidArgumentException('Pays de signup invalide.');

        try {
            Mail::to($email)->send(
                new TrialVerificationMail($managerName, $otp, strtolower($countryDefaults['language']))
            );
        } catch (\Throwable $e) {
            // Issue #5162 : sans visibilité sur le mailer résolu, un échec
            // d'envoi OTP (503 TRIAL_OTP_SEND_FAILED) est indiagnosticable en
            // prod. On logge transport + présence des variables requises
            // (jamais les secrets) pour un triage immédiat (même famille que
            // #5139/#5141 — egress Mailgun).
            $mailer = (string) config('mail.default', 'log');
            $mailerConfig = (array) config("mail.mailers.{$mailer}", []);
            $transport = (string) ($mailerConfig['transport'] ?? $mailer);

            Log::error('SelfServiceTrial: Failed to send OTP email', [
                'email' => $email,
                'mailer' => $mailer,
                'transport' => $transport,
                'mailgun_domain_configured' => $transport === 'mailgun'
                    ? filled($mailerConfig['domain'] ?? null)
                    : null,
                'mailgun_secret_configured' => $transport === 'mailgun'
                    ? filled($mailerConfig['secret'] ?? null)
                    : null,
                'from_address' => (string) config('mail.from.address', ''),
                'error' => $e->getMessage(),
            ]);
            // Issue #3057 : ne jamais répondre « code envoyé » si le mail a
            // échoué — la demande est conservée mais le client doit le savoir
            // (état honnête, pas d'écran OTP pour un code jamais parti).
            return false;
        }

        return true;
    }

    public function findExistingManager(string $email): ?Employee
    {
        // Issue #2678 — une erreur DB ne doit pas être confondue avec
        // « aucun manager existant » (sinon l'entreprise peut être créée deux
        // fois) : seules les recherches sans résultat renvoient null, les
        // erreurs remontent.
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO shared_tenants, public');
            }

            return Employee::query()
                ->where('email', $email)
                ->where('role', 'manager')
                ->first();
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
            $firstName = ucfirst($nameParts[0]);

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
                'country' => strtoupper(trim($validated['country'])),
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
            // Issue #2678 — ne pas avaler l'échec : sans CompanyRequest, le
            // verify échouera (OTP sans demande en attente) et l'utilisateur
            // reste dans un demi-état. L'échec fait échouer le signup.
            Log::error('SelfServiceTrial: Failed to create pending CompanyRequest record', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
