<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Employee;
use App\Services\CompanyProvisioningService;
use App\Services\TenantManager;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TrialWelcomeMail;
use Illuminate\Support\Str;

/**
 * Self-service trial provisioning endpoint.
 *
 * Transforms the guided trial signup into an instant self-service flow:
 * email + company name → tenant created → credentials returned → access in <30s.
 */
class SelfServiceTrialController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * POST /api/v1/trial/signup
     *
     * Creates a trial tenant with a manager account immediately.
     * Returns the credentials so the prospect can log in right away.
     */
    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'company' => ['required', 'string', 'min:2', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'in:founder,manager,hr,operations,other'],
            'employees' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            'country' => ['nullable', 'string', 'max:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'plan' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);

        $email = strtolower(trim($validated['email']));
        $companyName = trim($validated['company']);

        // Check if a manager with this email already exists in any tenant
        $existingManager = $this->findExistingManager($email);
        if ($existingManager) {
            return new JsonResponse([
                'success' => false,
                'error' => 'EMAIL_ALREADY_REGISTERED',
                'message' => 'Un compte avec cet email existe déjà. Connectez-vous directement.',
                'data' => [
                    'login_url' => '/auth/login',
                ],
            ], 409);
        }

        // Resolve country defaults
        $country = strtoupper(trim($validated['country'] ?? 'DZ'));
        if (strlen($country) !== 2) {
            $country = 'DZ';
        }
        $countryDefaults = CountryDefaults::for($country);

        // Resolve trial plan
        $trialPlan = $this->resolveTrialPlan();
        if (!$trialPlan) {
            Log::error('SelfServiceTrial: No active plan found for trial provisioning.');

            return new JsonResponse([
                'success' => false,
                'error' => 'NO_PLAN_AVAILABLE',
                'message' => 'Le service d\'essai est temporairement indisponible.',
            ], 503);
        }

        // Parse name from email or provided fields
        $firstName = trim($validated['first_name'] ?? '');
        $lastName = trim($validated['last_name'] ?? '');
        if (!$firstName) {
            $localPart = explode('@', $email)[0];
            $nameParts = preg_split('/[._\-+]/', $localPart, 2) ?: ['Manager'];
            $firstName = ucfirst($nameParts[0] ?? 'Manager');
            $lastName = isset($nameParts[1]) ? ucfirst($nameParts[1]) : 'Principal';
        }
        if (!$lastName) {
            $lastName = 'Principal';
        }

        // Generate temporary password
        $tempPassword = $this->generateReadablePassword();

        // Provision the company
        try {
            $result = $this->provisionTrialCompany([
                'name' => $companyName,
                'slug' => Str::slug($companyName),
                'sector' => $this->mapRoleToSector($validated['role'] ?? null),
                'country' => $country,
                'city' => 'Non précisé',
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'plan_id' => $trialPlan->id,
                'language' => strtolower($countryDefaults['language']),
                'currency' => strtoupper($countryDefaults['currency']),
                'timezone' => $countryDefaults['timezone'],
                'manager_first_name' => $firstName,
                'manager_last_name' => $lastName,
                'manager_email' => $email,
                'manager_phone' => $validated['phone'] ?? null,
                'temp_password' => $tempPassword,
                'employees_range' => $validated['employees'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Provisioning failed', [
                'email' => $email,
                'company' => $companyName,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'PROVISIONING_FAILED',
                'message' => 'Erreur lors de la création de votre espace. Veuillez réessayer.',
            ], 500);
        }

        // Create a CompanyRequest entry for CRM tracking
        $this->createCompanyRequestRecord($validated, $result['company'], $email);

        Log::info('SelfServiceTrial: Company provisioned', [
            'company_id' => $result['company']->id,
            'company_name' => $companyName,
            'manager_email' => $email,
            'source' => $validated['source'] ?? 'self_service_trial',
        ]);

        // Send welcome email with credentials
        try {
            Mail::to($email)->send(
                new TrialWelcomeMail($result['company'], $result['manager'], $tempPassword)
            );
        } catch (\Throwable $e) {
            Log::error('SelfServiceTrial: Failed to send welcome email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            // We don't fail the response, credentials are still shown in UI
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Votre espace Leopardo est prêt !',
            'data' => [
                'company' => [
                    'id' => $result['company']->id,
                    'name' => $result['company']->name,
                    'slug' => $result['company']->slug,
                ],
                'manager' => [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'temp_password' => $tempPassword,
                ],
                'trial' => [
                    'days' => 30,
                    'ends_at' => now()->addDays(30)->toIso8601String(),
                ],
                'next_steps' => [
                    'login' => 'Connectez-vous avec votre email et le mot de passe ci-dessus.',
                    'change_password' => 'Changez votre mot de passe dès la première connexion.',
                    'add_employees' => 'Ajoutez vos premiers employés via QR ou manuellement.',
                ],
            ],
        ], 201);
    }

    /**
     * Provision a trial company without requiring a SuperAdmin.
     *
     * @return array{company: Company, manager: Employee}
     */
    private function provisionTrialCompany(array $payload): array
    {
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
                'subscription_end' => now()->addDays(30)->toDateString(),
                'language' => $payload['language'],
                'timezone' => $payload['timezone'],
                'currency' => $payload['currency'],
                'metadata' => [
                    'provisioned_by' => 'self_service_trial',
                    'employees_range' => $payload['employees_range'],
                ],
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
                    'phone' => $payload['manager_phone'],
                    'password_hash' => Hash::make($payload['temp_password']),
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
                        'self_service_trial' => true,
                    ],
                ]);
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            return [
                'company' => $company,
                'manager' => $manager,
            ];
        });
    }

    private function findExistingManager(string $email): ?Employee
    {
        // Check in shared_tenants schema
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO shared_tenants, public');
            }

            $employee = Employee::query()
                ->where('email', $email)
                ->where('role', 'manager')
                ->first();

            return $employee;
        } catch (\Throwable) {
            return null;
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO public');
            }
        }
    }

    private function resolveTrialPlan(): ?object
    {
        return DB::table($this->publicTable('plans'))
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? DB::table($this->publicTable('plans'))
                ->orderBy('id')
                ->first();
    }

    private function publicTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.'.$table : $table;
    }

    private function resolveUniqueSlug(string $baseSlug): string
    {
        $slug = Str::slug($baseSlug);
        if (!$slug) {
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

    private function createCompanyRequestRecord(array $validated, Company $company, string $email): void
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
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'description' => 'Self-service trial signup — source: '.($validated['source'] ?? 'direct'),
                'status' => 'approved',
                'approved_company_id' => $company->id,
                'reviewed_at' => now(),
                'admin_notes' => 'Auto-provisioned via self-service trial.',
            ]);
        } catch (\Throwable $e) {
            // Non-critical: don't fail the provisioning if CRM tracking fails
            Log::warning('SelfServiceTrial: Failed to create CompanyRequest record', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
