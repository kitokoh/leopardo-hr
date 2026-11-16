<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OnboardingQrControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_can_scan_employee_qr_and_create_employee_from_prefill(): void
    {
        Mail::fake();

        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'commerce',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = new Employee([
            'first_name' => 'Meriem',
            'last_name' => 'RH',
            'email' => 'manager@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ])->save();

        $externalEmployee = Employee::withoutGlobalScopes()->forceCreate([
            'first_name' => 'Amina',
            'last_name' => 'Belaid',
            'email' => 'amina.personal@example.test',
            'personal_email' => 'amina.personal@example.test',
            'personal_phone' => '+213600000111',
            'password_hash' => Hash::make('password123'),
        ]);
        $externalEmployee->forceFill([
            'company_id' => $companyB->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $qr = $this
            ->actingAs($externalEmployee, 'sanctum')
            ->getJson('/api/v1/me/qr-profile')
            ->assertOk()
            ->json('data.token');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => $qr,
            ])
            ->assertOk()
            ->assertJsonPath('data.prefill.first_name', 'Amina')
            ->assertJsonPath('data.prefill.personal_email', 'amina.personal@example.test');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/create-employee', [
                'qr_token' => $qr,
                'email' => 'amina.pro@a.test',
                'matricule' => 'A-QR-001',
                'contract_start' => '2026-05-27',
                'salary_type' => 'fixed',
                'salary_base' => 85000,
                'extra_data' => [
                    'department' => 'Operations',
                    'job_title' => 'Assistante terrain',
                    'work_location' => 'Alger Est',
                ],
                'send_invitation' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'amina.pro@a.test')
            ->assertJsonPath('data.extra_data.department', 'Operations');

        $this->assertDatabaseHas('employees', [
            'company_id' => $companyA->id,
            'email' => 'amina.pro@a.test',
            'matricule' => 'A-QR-001',
            'salary_base' => 85000,
        ]);
    }

    public function test_employee_qr_rejects_tampered_token(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = new Employee([
            'email' => 'manager@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ])->save();

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => 'invalid.payload',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr_token']);
    }

    public function test_company_onboarding_returns_payload_for_principal_manager(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Principal',
            'email' => 'principal@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        $this
            ->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/company/qr-onboarding')
            ->assertOk()
            ->assertJsonPath('data.type', 'company_onboarding')
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.name', 'Company A')
            ->assertJsonPath('data.issued_by.id', $manager->id)
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);
    }

    /**
     * Le jeton QR est autoporteur : sa validité ne dépend ni de la base ni d'un
     * état serveur, seulement de la signature HMAC et de la date `expires_at`
     * qu'il contient. Aucun test ne couvrait cette échéance — un jeton signé
     * valablement mais EXPIRÉ doit être refusé, sinon un QR photographié reste
     * exploitable indéfiniment pour préremplir une fiche employé.
     *
     * On forge ici un jeton avec la même clé que le service : c'est la seule
     * façon de tester l'expiration sans attendre 30 jours (le service signe en
     * `now()->addDays(30)` et n'expose aucun paramètre de TTL).
     */
    public function test_scan_employee_rejects_an_expired_qr_token(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = new Employee([
            'first_name' => 'Meriem',
            'last_name' => 'RH',
            'email' => 'manager@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ])->save();

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => $this->forgeQrToken([
                    'v' => 1,
                    'type' => 'employee_profile',
                    'employee' => [
                        'id' => 999,
                        'first_name' => 'Expire',
                        'last_name' => 'Test',
                        'email' => 'expire@example.test',
                    ],
                    'issued_at' => now()->subDays(60)->toIso8601String(),
                    'expires_at' => now()->subDay()->toIso8601String(),
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr_token'])
            ->assertJsonPath('errors.qr_token.0', 'QR expire.');
    }

    /**
     * Un jeton correctement signé mais d'un AUTRE type ne doit pas être accepté
     * par la route `scan-employee` : sans ce contrôle, le QR « onboarding
     * société » (qui porte le nom et l'e-mail du propriétaire, pas d'un
     * employé) pourrait servir de préremplissage de fiche employé.
     */
    public function test_scan_employee_rejects_a_token_of_another_type(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = new Employee([
            'first_name' => 'Meriem',
            'last_name' => 'RH',
            'email' => 'manager@a.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ])->save();

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => $this->forgeQrToken([
                    'v' => 1,
                    'type' => 'company_onboarding',
                    'company' => ['id' => $company->id, 'name' => 'Company A'],
                    'issued_at' => now()->toIso8601String(),
                    'expires_at' => now()->addDays(30)->toIso8601String(),
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr_token'])
            ->assertJsonPath('errors.qr_token.0', 'Type de QR incompatible.');
    }

    /**
     * Signe un payload avec la clé applicative, exactement comme
     * `OnboardingQrService::sign()` — pour exercer les branches que la fabrique
     * du service (TTL fixe de 30 jours, type figé) ne permet pas d'atteindre.
     *
     * @param  array<string, mixed>  $payload
     */
    private function forgeQrToken(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, (string) config('app.key'));

        return $encoded.'.'.$signature;
    }

    public function test_company_onboarding_forbidden_for_non_manager(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = new Employee([
            'first_name' => 'Sami',
            'last_name' => 'Employe',
            'email' => 'employe@a.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $this
            ->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/company/qr-onboarding')
            ->assertStatus(403);
    }
}
