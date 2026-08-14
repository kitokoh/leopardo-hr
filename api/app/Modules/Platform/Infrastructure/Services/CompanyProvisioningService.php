<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use App\Support\CountryDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyProvisioningService
{
    public function __construct(
        private readonly UserInvitationService $invitationService,
        private readonly TenantManager $tenantManager,
        private readonly SectorTemplateService $sectorTemplateService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{company: Company, manager: Employee}
     */
    public function provisionSharedCompany(array $payload, SuperAdmin $superAdmin): array
    {
        // MULTI-PAYS (#1867) : le pays légal est OBLIGATOIRE et doit être un
        // pays supporté — aucun fallback silencieux vers DZ.
        $countryDefaults = CountryDefaults::find($payload['country'] ?? null);
        if ($countryDefaults === null) {
            throw ValidationException::withMessages([
                'country' => ['Le pays légal du tenant est obligatoire et doit être supporté ('.implode(', ', array_keys(CountryDefaults::all())).').'],
            ]);
        }

        $payload['country'] = $countryDefaults['country'];
        $payload['language'] = strtolower((string) ($payload['language'] ?? $countryDefaults['language']));
        $payload['currency'] = strtoupper((string) ($payload['currency'] ?? $countryDefaults['currency']));
        $payload['timezone'] = $payload['timezone'] ?? $countryDefaults['timezone'];

        /** @var array{company: Company, manager: Employee} $result */
        $result = DB::transaction(function () use ($payload): array {
            $plan = DB::table('plans')->where('id', $payload['plan_id'])->first();
            $trialDays = (int) ($plan->trial_days ?? 14);
            $slug = $this->resolveUniqueSlug((string) ($payload['slug'] ?? Str::slug((string) $payload['name'])));

            $company = Company::query()->create([
                'name' => $payload['name'],
                'slug' => $slug,
                'sector' => $payload['sector'],
                'country' => $payload['country'],
                'city' => $payload['city'],
                'address' => $payload['address'] ?? null,
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'plan_id' => $payload['plan_id'],
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => $payload['status'] ?? 'trial',
                'subscription_start' => $payload['subscription_start'] ?? now()->toDateString(),
                'subscription_end' => $payload['subscription_end'] ?? now()->addDays($trialDays)->toDateString(),
                'language' => $payload['language'],
                'timezone' => $payload['timezone'],
                'currency' => $payload['currency'],
                'notes' => $payload['notes'] ?? null,
            ]);

            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            $this->tenantManager->setTenant($company);

            try {
                /** @var Employee $manager */
                $manager = Employee::query()->create([
                    'company_id' => $company->id,
                    'first_name' => $payload['manager_first_name'],
                    'last_name' => $payload['manager_last_name'],
                    'email' => $payload['manager_email'],
                    'phone' => $payload['manager_phone'] ?? null,
                    'password_hash' => bcrypt(Str::random(32)),
                    'role' => 'manager',
                    'manager_role' => 'principal',
                    'status' => 'active',
                    'contract_type' => 'CDI',
                    'contract_start' => now()->toDateString(),
                    'salary_type' => 'fixed',
                    'salary_base' => 0,
                    'biometric_face_enabled' => false,
                    'biometric_fingerprint_enabled' => false,
                    'extra_data' => [
                        'job_title' => 'Manager principal',
                    ],
                ]);

                // P1.3: Apply sectorial template
                $this->sectorTemplateService->applyTemplate($company);
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            return [
                'company' => $company,
                'manager' => $manager,
            ];
        });

        $this->invitationService->createAndSend(
            company: $result['company'],
            employee: $result['manager'],
            invitedByType: 'super_admin',
            invitedByEmail: (string) $superAdmin->email,
        );

        return $result;
    }

    private function resolveUniqueSlug(string $baseSlug): string
    {
        $slug = Str::slug($baseSlug);
        $candidate = $slug;
        $suffix = 1;

        while (Company::query()->where('slug', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$slug}-{$suffix}";
        }

        return $candidate;
    }
}
