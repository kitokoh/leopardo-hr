<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyProvisioningService
{
    public function __construct(
        private readonly UserInvitationService $invitationService,
    ) {
    }

    /**
     * @return array{company: Company, manager: Employee}
     */
    public function provisionSharedCompany(array $payload, SuperAdmin $superAdmin): array
    {
        DB::statement('SET search_path TO public');

        $result = DB::transaction(function () use ($payload): array {
            $plan = DB::table('plans')->where('id', $payload['plan_id'])->first();
            $trialDays = (int) ($plan->trial_days ?? 14);
            $slug = $this->resolveUniqueSlug($payload['slug'] ?? Str::slug($payload['name']));

            $company = Company::query()->create([
                'name' => $payload['name'],
                'slug' => $slug,
                'sector' => $payload['sector'],
                'country' => strtoupper($payload['country']),
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
                'language' => $payload['language'] ?? 'fr',
                'timezone' => $payload['timezone'] ?? 'Africa/Algiers',
                'currency' => $payload['currency'] ?? 'DZD',
                'notes' => $payload['notes'] ?? null,
            ]);

            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            DB::statement('SET search_path TO shared_tenants,public');

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

            DB::statement('SET search_path TO public');

            return [
                'company' => $company,
                'manager' => $manager,
            ];
        });

        $this->invitationService->createAndSend(
            company: $result['company'],
            employee: $result['manager'],
            invitedByType: 'super_admin',
            invitedByEmail: $superAdmin->email,
        );

        DB::statement('SET search_path TO shared_tenants,public');

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
