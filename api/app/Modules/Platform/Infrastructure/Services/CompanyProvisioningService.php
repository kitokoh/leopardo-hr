<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use App\Support\CountryDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'country' => ['Le pays légal du tenant est obligatoire et doit être supporté ('.implode(', ', array_column(CountryDefaults::all(), 'country')).').'],
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

            $companyData = [
                'name' => $payload['name'],
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
            ];

            $company = $this->createCompanyWithUniqueSlug($companyData, $slug);

            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            $this->tenantManager->setTenant($company);

            try {
                /** @var Employee $manager */
                $manager = new Employee([
                    'first_name' => $payload['manager_first_name'],
                    'last_name' => $payload['manager_last_name'],
                    'email' => $payload['manager_email'],
                    'phone' => $payload['manager_phone'] ?? null,
                    'contract_type' => 'CDI',
                    'contract_start' => now()->toDateString(),
                    'salary_type' => 'fixed',
                    'biometric_face_enabled' => false,
                    'biometric_fingerprint_enabled' => false,
                    'extra_data' => [
                        'job_title' => 'Manager principal',
                    ],
                ]);
                // Sensitive fields set explicitly (not mass-assignable, #3677).
                $manager->company_id = $company->id;
                $manager->role = 'manager';
                $manager->manager_role = 'principal';
                $manager->status = 'active';
                $manager->salary_base = 0;
                // Issue #4496 : password_hash non mass-assignable.
                $manager->password_hash = bcrypt(Str::random(32));
                $manager->save();

                // P1.3: Apply sectorial template
                $this->sectorTemplateService->applyTemplate($company);

                // #4929 : seed des 10 étapes d'onboarding AU PROVISIONING
                // (avant : différé à la première lecture de la checklist —
                // le PATCH complete/skip pouvait tomber sur un 404 si le
                // client sautait le GET). Le seed est idempotent (dédup par
                // step_key) et s'exécute dans le contexte tenant du company.
                app(SeedDefaultSteps::class)->execute($company->id);
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

    /**
     * Issue #3811 — création société résiliente à la course sur companies.slug.
     *
     * `resolveUniqueSlug()` est un check-then-create : entre le `exists()` et
     * l'insert, un provisionnement concurrent peut gagner la course (index
     * unique companies.slug) → QueryException 23505. La création s'exécute
     * dans un `DB::transaction` imbriqué (savepoint) : PostgreSQL aborte la
     * transaction courante sur erreur, le savepoint absorbe l'abort et permet
     * de re-résoudre un slug libre puis de retenter UNE fois — jamais de 500
     * sur collision de slug (pattern 23505, cf. PartnerService #3238).
     *
     * @param  array<string, mixed>  $data
     */
    private function createCompanyWithUniqueSlug(array $data, string $slug): Company
    {
        try {
            return DB::transaction(fn (): Company => Company::query()->create($data + ['slug' => $slug]));
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            Log::warning("Company slug race on '{$slug}' — re-resolving unique slug and retrying once.");

            return DB::transaction(
                fn (): Company => Company::query()->create($data + ['slug' => $this->resolveUniqueSlug($slug)])
            );
        }
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
