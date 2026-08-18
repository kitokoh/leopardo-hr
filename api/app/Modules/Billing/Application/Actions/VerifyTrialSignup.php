<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\TenantManager;
use App\Events\CompanyCreated;
use App\Jobs\SendTrialDripEmailJob;
use App\Mail\TrialWelcomeMail;
use App\Modules\Billing\Infrastructure\Services\PartnerService;
use App\Support\CountryDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Vérifie le code OTP d'une demande d'essai self-service et provisionne
 * immédiatement le tenant (company + manager) si le code est valide.
 */
class VerifyTrialSignup
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PartnerService $partnerService,
        private readonly RequestTrialSignup $requestTrialSignup,
    ) {}

    /**
     * @return array{success: true, company: Company, manager: Employee, manager_email: string, first_name: string, last_name: string}|array{success: false, error: string, message: string, status: int}
     */
    public function execute(string $email, string $code): array
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        // QA #2996 — verrou atomique anti double-provisioning : deux POST
        // /trial/verify simultanés avec le même OTP valide créaient 2 tenants
        // + 2 managers pour le même email (lecture pending → provisioning →
        // approved sans verrou). La CompanyRequest est maintenant CLAIMÉE en
        // `processing` sous transaction (lockForUpdate) : le 2e appel voit un
        // statut non-pending et refuse, sans jamais provisionner deux fois.
        $claimed = DB::transaction(function () use ($email, $code): ?CompanyRequest {
            $request = CompanyRequest::query()
                ->where('email', $email)
                ->where('status', 'pending')
                ->where('verification_token', $code)
                ->where('verification_expires_at', '>=', now())
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                return null;
            }

            $request->update(['status' => 'processing']);

            return $request;
        });

        if ($claimed === null) {
            // Distinguer « code invalide/expiré » de « déjà traité » : un
            // code valide déjà consommé ne doit pas dire INVALID au client.
            $existing = CompanyRequest::query()
                ->where('email', $email)
                ->where('verification_token', $code)
                ->first();

            if ($existing !== null && $existing->status !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'ALREADY_PROCESSED',
                    'message' => __('errors.ALREADY_PROCESSED'),
                    'status' => 409,
                ];
            }

            return [
                'success' => false,
                'error' => 'INVALID_OR_EXPIRED_CODE',
                'message' => __('errors.INVALID_OR_EXPIRED_CODE'),
                'status' => 400,
            ];
        }

        $companyRequest = $claimed;
        $payload = $companyRequest->signup_payload ?? [];
        $companyName = (string) ($companyRequest->company_name ?? '');

        // Anti-énumération (#3945) : la détection « email déjà enregistré »
        // vit ici — l'OTP valide prouve la possession de la boîte mail, donc
        // la réponse ne peut plus servir à énumérer des comptes sur
        // l'endpoint public /trial/signup.
        $existingManager = $this->requestTrialSignup->findExistingManager($email);
        if ($existingManager !== null) {
            // Terminer proprement la demande (état terminal, pas de reprocessing).
            $companyRequest->update(['status' => 'rejected']);
            Log::info('trial.verify_duplicate_manager', ['email' => $email]);

            return [
                'success' => false,
                'error' => 'EMAIL_ALREADY_REGISTERED',
                'message' => __('errors.EMAIL_ALREADY_REGISTERED'),
                'status' => 409,
            ];
        }

        // MULTI-PAYS (#1867/#1950) : le pays vient du signup validé (règle
        // SupportedCountry) — résolution STRICTE, aucun fallback silencieux
        // vers DZ (invariant 10). Un payload hérité sans pays valide → 422.
        $country = strtoupper(trim((string) ($payload['country'] ?? '')));
        $countryDefaults = CountryDefaults::find($country);
        if ($countryDefaults === null) {
            return [
                'success' => false,
                'error' => 'INVALID_COUNTRY',
                'message' => __('errors.INVALID_COUNTRY'),
                'status' => 422,
            ];
        }

        $trialPlan = $this->resolveTrialPlan();
        if (! $trialPlan) {
            Log::error('SelfServiceTrial: No active plan found for trial provisioning.');

            return [
                'success' => false,
                'error' => 'NO_PLAN_AVAILABLE',
                'message' => __('errors.NO_PLAN_AVAILABLE'),
                'status' => 503,
            ];
        }

        [$firstName, $lastName] = $this->requestTrialSignup->managerNameParts($payload, $email);
        $tempPassword = $this->generateReadablePassword();

        try {
            /** @var object{id: mixed} $trialPlan */
            $result = $this->provisionTrialCompany([
                'name' => $companyName,
                'slug' => Str::slug($companyName),
                'sector' => $this->mapRoleToSector($payload['role'] ?? null),
                'country' => $country,
                'city' => 'Non précisé',
                'email' => $email,
                'phone' => $payload['phone'] ?? null,
                'plan_id' => $trialPlan->id,
                'language' => strtolower($countryDefaults['language']),
                'currency' => strtoupper($countryDefaults['currency']),
                'timezone' => $countryDefaults['timezone'],
                'manager_first_name' => $firstName,
                'manager_last_name' => $lastName,
                'manager_email' => $email,
                'manager_phone' => $payload['phone'] ?? null,
                'temp_password' => $tempPassword,
                'employees_range' => $payload['employees'] ?? null,
                'referral_code' => $payload['referral_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Provisioning failed', [
                'email' => $email,
                'company' => $companyName,
                'error' => $e->getMessage(),
            ]);

            // QA #2996 — libérer la demande pour permettre un retry (le claim
            // `processing` ne doit pas bloquer définitivement le parcours).
            try {
                $companyRequest->update(['status' => 'pending']);
            } catch (\Throwable $revertError) {
                Log::error('SelfServiceTrial: Failed to revert claim after provisioning failure', [
                    'email' => $email,
                    'error' => $revertError->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'PROVISIONING_FAILED',
                'message' => __('errors.PROVISIONING_FAILED'),
                'status' => 500,
            ];
        }

        event(new CompanyCreated($result['company']));

        $companyRequest->update([
            'status' => 'approved',
            'approved_company_id' => $result['company']->id,
            'verification_token' => null,
        ]);

        Log::info('SelfServiceTrial: Company provisioned after verification', [
            'company_id' => $result['company']->id,
            'company_name' => $companyName,
            'manager_email' => $email,
            'source' => $payload['source'] ?? 'self_service_trial',
        ]);

        try {
            Mail::to($email)->send(
                new TrialWelcomeMail($result['company'], $result['manager'], $tempPassword)
            );
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Failed to send welcome email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        SendTrialDripEmailJob::dispatch($result['company'], 1)->delay(now()->addDay());
        SendTrialDripEmailJob::dispatch($result['company'], 3)->delay(now()->addDays(3));
        SendTrialDripEmailJob::dispatch($result['company'], 7)->delay(now()->addDays(7));

        return [
            'success' => true,
            'company' => $result['company'],
            'manager' => $result['manager'],
            'manager_email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{company: Company, manager: Employee}
     */
    private function provisionTrialCompany(array $payload): array
    {
        // #3895 : resolveUniqueSlug() (while-exists) n'est pas sérialisé entre
        // deux signups simultanés au même nom — la violation d'unicité
        // companies.slug (23505) est rattrapée par un retry borné avec un
        // nouveau candidat au lieu d'un 500 (rare mais réel sous charge).
        $attempts = 0;

        do {
            try {
                return DB::transaction(function () use ($payload): array {
                    $slug = $this->resolveUniqueSlug($payload['slug']);

                    $company = Company::query()->create([
                        'name' => $payload['name'],
                        'slug' => $slug,
                        'sector' => $payload['sector'],
                        'country' => $payload['country'],
                        'city' => $payload['city'],
                        'email' => $payload['email'],
                        'phone' => $payload['phone'],
                        'plan_id' => $payload['plan_id'],
                        'schema_name' => 'shared_tenants',
                        'tenancy_type' => 'shared',
                        'status' => 'trial',
                        'subscription_start' => now()->toDateString(),
                        'subscription_end' => now()->addDays((int) config('billing.trial_days'))->toDateString(),
                        'language' => $payload['language'],
                        'timezone' => $payload['timezone'],
                        'currency' => $payload['currency'],
                        'metadata' => [
                            'provisioned_by' => 'self_service_trial',
                            'employees_range' => $payload['employees_range'],
                        ],
                    ]);

                    if (! empty($payload['referral_code'])) {
                        $this->partnerService->attributeCompanyToPartner($company, $payload['referral_code']);
                    }

                    if (DB::getDriverName() === 'pgsql') {
                        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
                    }
                    $this->tenantManager->setTenant($company);

                    try {
                        /** @var Employee $manager */
                        $manager = new Employee([
                            'first_name' => $payload['manager_first_name'],
                            'last_name' => $payload['manager_last_name'],
                            'email' => $payload['manager_email'],
                            'phone' => $payload['manager_phone'],
                            'contract_type' => 'CDI',
                            'contract_start' => now()->toDateString(),
                            'salary_type' => 'fixed',
                            'biometric_face_enabled' => false,
                            'biometric_fingerprint_enabled' => false,
                            'extra_data' => [
                                'job_title' => 'Manager principal',
                                'self_service_trial' => true,
                            ],
                        ]);
                        $manager->forceFill([
                            'company_id' => $company->id,
                            // Issue #4496 : password_hash non mass-assignable.
                            'password_hash' => Hash::make($payload['temp_password']),
                            'role' => 'manager',
                            'manager_role' => 'principal',
                            'status' => 'active',
                            'salary_base' => 0,
                        ])->save();
                    } finally {
                        $this->tenantManager->resetToPrevious();
                    }

                    return [
                        'company' => $company,
                        'manager' => $manager,
                    ];
                });
            } catch (QueryException $e) {
                if ($e->getCode() !== '23505' || ++$attempts >= 5) {
                    throw $e;
                }
                Log::warning('trial.signup.slug_collision_retry', ['attempt' => $attempts, 'base_slug' => $payload['slug']]);
            }
        } while (true);
    }

    private function resolveTrialPlan(): ?object
    {
        $plan = DB::table($this->publicTable('plans'))
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? DB::table($this->publicTable('plans'))
                ->orderBy('id')
                ->first();

        if ($plan) {
            return $plan;
        }

        return $this->createFallbackTrialPlan();
    }

    private function createFallbackTrialPlan(): ?object
    {
        try {
            $id = DB::table($this->publicTable('plans'))->insertGetId([
                'name' => 'Trial',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_employees' => 50,
                'features' => json_encode([
                    'rh' => true,
                    'tasks' => true,
                    'attendance' => true,
                    'mobile_apps' => true,
                ], JSON_THROW_ON_ERROR),
                'trial_days' => (int) config('billing.trial_days'),
                'is_active' => true,
            ]);

            return DB::table($this->publicTable('plans'))->where('id', $id)->first();
        } catch (\Throwable $e) {
            Log::warning('SelfServiceTrial: unable to create fallback trial plan', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function publicTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.'.$table : $table;
    }

    protected function resolveUniqueSlug(string $baseSlug): string
    {
        $slug = Str::slug($baseSlug);
        if (! $slug) {
            $slug = 'company-'.Str::random(6);
        }
        $candidate = $slug;
        $suffix = 1;

        while (Company::query()->where('slug', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$slug}-{$suffix}";
        }

        return $candidate;
    }

    /**
     * Generate a readable temporary password (12 chars, mixed case + digits).
     */
    private function generateReadablePassword(): string
    {
        $words = ['Leo', 'Rh', 'Go', 'Pro', 'Top', 'Biz', 'App', 'Hub'];
        $word = $words[array_rand($words)];
        $digits = str_pad((string) random_int(100, 9999), 4, '0', STR_PAD_LEFT);
        $suffix = chr(random_int(65, 90)); // A-Z

        return $word.$digits.$suffix.'!';
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
}
