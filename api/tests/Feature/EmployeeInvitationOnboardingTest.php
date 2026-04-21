<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\Employee;
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

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
            'password' => 'password456',
            'password_confirmation' => 'password456',
        ])->assertRedirect(route('login'));

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->where('email', 'karim.aouad@company.test')->firstOrFail();
        $this->assertTrue(Hash::check('password456', $employee->password_hash));
        $this->assertNotNull($employee->invitation_accepted_at);
        $this->assertNotNull($employee->email_verified_at);
    }
}
