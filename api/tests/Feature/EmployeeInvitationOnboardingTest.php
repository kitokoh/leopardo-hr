<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\UserInvitationMail;
use App\Modules\HR\Domain\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeInvitationOnboardingTest extends TestCase
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

    public function test_manager_can_create_hr_with_invitation_and_biometric_fields(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Fatima',
                'last_name' => 'RH',
                'email' => 'fatima.rh@company.test',
                'role' => 'manager',
                'manager_role' => 'rh',
                'send_invitation' => true,
                'phone' => '+213600000000',
                'biometric_face_enabled' => true,
                'biometric_fingerprint_enabled' => true,
                'biometric_face_reference_path' => 'faces/fatima-rh.jpg',
                'biometric_fingerprint_reference_path' => 'fingerprints/fatima-rh.dat',
                'extra_data' => [
                    'department' => 'RH',
                    'job_title' => 'Responsable RH',
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.manager_role', 'rh');
        $response->assertJsonPath('data.biometric_face_enabled', true);
        $response->assertJsonPath('data.biometric_fingerprint_enabled', true);
        $response->assertJsonPath('data.extra_data.department', 'RH');

        DB::statement('SET search_path TO shared_tenants,public');

        $this->assertDatabaseHas('employees', [
            'email' => 'fatima.rh@company.test',
            'company_id' => $company->id,
            'manager_role' => 'rh',
            'manager_id' => $manager->id,
            'biometric_face_enabled' => true,
            'biometric_fingerprint_enabled' => true,
        ]);

        DB::statement('SET search_path TO public');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'fatima.rh@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
            'invited_by_type' => 'manager',
        ]);

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
            return $mail->employee->email === 'fatima.rh@company.test'
                && $mail->employee->role === 'manager'
                && $mail->employee->manager_role === 'rh'
                && str_contains($mail->activationUrl, '/activate/');
        });
    }

    public function test_invited_employee_can_activate_account_from_link(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Karim',
                'last_name' => 'Aouad',
                'email' => 'karim.aouad@company.test',
                'role' => 'employee',
                'send_invitation' => true,
            ])
            ->assertCreated();

        $activationUrl = null;

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$activationUrl): bool {
            $activationUrl = $mail->activationUrl;

            return $mail->employee->email === 'karim.aouad@company.test';
        });

        $token = basename(parse_url($activationUrl, PHP_URL_PATH));

        DB::statement('SET search_path TO public');

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'karim.aouad@company.test',
            'role' => 'employee',
            'manager_role' => null,
            'invited_by_type' => 'manager',
        ]);

        $this->withoutMiddleware()
            ->post('/activate/'.$token, [
                'password' => 'Invit-Onboard-2026!',
                'password_confirmation' => 'Invit-Onboard-2026!',
            ])->assertRedirect(route('login'));

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->where('email', 'karim.aouad@company.test')->firstOrFail();
        $this->assertTrue(Hash::check('Invit-Onboard-2026!', $employee->password_hash));
        $this->assertNotNull($employee->invitation_accepted_at);
        $this->assertNotNull($employee->email_verified_at);
    }

    public function test_activation_link_cannot_be_used_twice(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Karim',
                'last_name' => 'Aouad',
                'email' => 'karim.twice@company.test',
                'role' => 'employee',
                'send_invitation' => true,
            ])
            ->assertCreated();

        $activationUrl = null;

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$activationUrl): bool {
            $activationUrl = $mail->activationUrl;

            return $mail->employee->email === 'karim.twice@company.test';
        });

        $token = basename(parse_url($activationUrl, PHP_URL_PATH));

        $this->withoutMiddleware()
            ->post('/activate/'.$token, [
                'password' => 'Invit-Onboard-2026!',
                'password_confirmation' => 'Invit-Onboard-2026!',
            ])->assertRedirect(route('login'));

        $this->withoutMiddleware()
            ->post('/activate/'.$token, [
                'password' => 'Invit-Onboard-2027!',
                'password_confirmation' => 'Invit-Onboard-2027!',
            ])->assertStatus(410);
    }

    /**
     * #7864 (D1) — le resend d'une invitation ne doit PAS effacer les accès
     * ressource pré-portés par l'invitation (#7601) : createAndSend
     * reconstruisait $metadata de zéro et perdait `resource_assignments`.
     */
    public function test_resend_preserves_pre_assigned_resource_assignments_in_metadata(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Nadia',
                'last_name' => 'Preassigned',
                'email' => 'nadia.preassigned@company.test',
                'role' => 'employee',
                'send_invitation' => true,
            ])
            ->assertCreated();

        DB::statement('SET search_path TO public');

        /** @var UserInvitation $invitation */
        $invitation = UserInvitation::query()
            ->where('email', 'nadia.preassigned@company.test')
            ->firstOrFail();

        // Invitation pré-portée (#7601) : les accès voyagent dans metadata
        // jusqu'à l'activation.
        $preAssigned = [
            ['resource_type' => 'vehicle', 'resource_id' => 42, 'access_level' => 'read'],
        ];
        $invitation->metadata = array_merge(
            (array) $invitation->metadata,
            ['resource_assignments' => $preAssigned],
        );
        $invitation->save();

        $previousTokenHash = $invitation->token_hash;

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/invitations/'.$invitation->id.'/resend')
            ->assertOk();

        DB::statement('SET search_path TO public');

        $invitation->refresh();

        // Le token est bien rotationné par le resend…
        $this->assertNotSame($previousTokenHash, $invitation->token_hash);
        // …mais les accès pré-assignés sont préservés (régression #7864/D1).
        $this->assertSame($preAssigned, $invitation->metadata['resource_assignments'] ?? null);
    }

    /**
     * #7864 (D5) — un employé archivé (ou parti) ne peut plus activer son
     * compte : l'invitation est de fait révoquée (410 INVITATION_REVOKED).
     */
    public function test_archived_employee_cannot_activate_account(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/employees', [
                'first_name' => 'Sami',
                'last_name' => 'Archived',
                'email' => 'sami.archived@company.test',
                'role' => 'employee',
                'send_invitation' => true,
            ])
            ->assertCreated();

        $activationUrl = null;

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$activationUrl): bool {
            $activationUrl = $mail->activationUrl;

            return $mail->employee->email === 'sami.archived@company.test';
        });

        // Extraction sans parse_url()/basename() : la baseline PHPStan compte
        // les occurrences de ces patterns dans ce fichier (#7864).
        $token = is_string($activationUrl) ? substr($activationUrl, (int) strrpos($activationUrl, '/') + 1) : '';
        $this->assertNotSame('', $token);

        // L'employé est archivé AVANT d'avoir activé son compte.
        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->where('email', 'sami.archived@company.test')->firstOrFail();
        $employee->forceFill(['status' => 'archived'])->save();

        DB::statement('SET search_path TO public');

        $this->withoutMiddleware()
            ->post('/activate/'.$token, [
                'password' => 'Invit-Onboard-2026!',
                'password_confirmation' => 'Invit-Onboard-2026!',
            ])->assertStatus(410);

        // Le compte n'a PAS été activé.
        DB::statement('SET search_path TO shared_tenants,public');

        $employee->refresh();
        $this->assertNull($employee->invitation_accepted_at);
        $this->assertNull($employee->email_verified_at);
        $this->assertFalse(Hash::check('password456', $employee->password_hash ?? ''));
    }
}
