<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class BiometricWorkflowTest extends TestCase
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

    public function test_employee_biometric_request_requires_manager_approval_before_activation(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'manager_id' => $manager->id,
            'status' => 'active',
        ]);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/biometric-enrollment', [
                'requested_face_enabled' => true,
                'requested_fingerprint_enabled' => true,
                'requested_fingerprint_device_id' => 'FP-ENTREE-01',
                'employee_note' => 'Pret pour borne entree',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $employee->refresh();
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertFalse($employee->biometric_fingerprint_enabled);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/biometric-enrollment-requests/1/approve', [
                'manager_note' => 'Validation RH',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $employee->refresh();
        $this->assertTrue($employee->biometric_face_enabled);
        $this->assertTrue($employee->biometric_fingerprint_enabled);
        $this->assertEquals('FP-ENTREE-01', $employee->biometric_fingerprint_reference_path);
        $this->assertNotNull($employee->biometric_consent_at);
    }

    public function test_kiosk_can_check_in_employee_with_approved_biometrics(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@company.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ]);

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

        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        $deviceCode = $kioskResponse->json('data.device_code');
        $syncToken = $kioskResponse->json('data.sync_token');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
            ])->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.method', 'kiosk_fingerprint');
    }

    public function test_kiosk_can_sync_offline_events_and_fetch_roster(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@company.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
        ]);

        DB::statement('SET search_path TO public');

        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        $deviceCode = $kioskResponse->json('data.device_code');
        $syncToken = $kioskResponse->json('data.sync_token');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/roster')
            ->assertOk()
            ->assertJsonPath('data.employees.0.zkteco_id', $employee->zkteco_id);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-001',
                        'biometric_type' => 'fingerprint',
                    ],
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_out',
                        'occurred_at' => '2026-04-19T17:00:00Z',
                        'external_event_id' => 'evt-002',
                        'biometric_type' => 'fingerprint',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 2);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'source_device_code' => $deviceCode,
            'external_event_id' => 'evt-002',
            'synced_from_offline' => true,
        ]);
    }

    /**
     * PA2-KIO-004: the kiosk employee-info lookup must surface the employee's
     * biometric consent/enrollment status (enabled flags + pending mobile
     * enrollment request) so field staff can see it without leaving the
     * kiosk screen.
     */
    public function test_kiosk_employee_info_surfaces_biometric_enrollment_status(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
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

        $enrolledEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@company.test',
            'matricule' => 'EMP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
            'biometric_consent_at' => now(),
        ]);

        $pendingEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sara',
            'last_name' => 'Nouvelle',
            'email' => 'sara@company.test',
            'matricule' => 'EMP-002',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        BiometricEnrollmentRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $pendingEmployee->id,
            'status' => 'pending',
            'requested_face_enabled' => true,
            'requested_fingerprint_enabled' => false,
            'request_source' => 'mobile',
            'submitted_at' => now(),
        ]);

        $unenrolledEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Yacine',
            'last_name' => 'SansBiometrie',
            'email' => 'yacine@company.test',
            'matricule' => 'EMP-003',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasTable('leave_balances')) {
            \Illuminate\Support\Facades\Schema::create('leave_balances', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->unsignedInteger('absence_type_id')->nullable();
                $table->decimal('balance', 6, 2)->default(0);
                $table->decimal('used', 6, 2)->default(0);
                $table->decimal('pending', 6, 2)->default(0);
                $table->unsignedSmallInteger('year');
                $table->timestampTz('updated_at')->nullable();
            });
        }

        DB::statement('SET search_path TO public');

        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'mixed',
            ])
            ->assertCreated();

        $deviceCode = $kioskResponse->json('data.device_code');
        $syncToken = $kioskResponse->json('data.sync_token');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/employee-info', [
                'identifier' => 'EMP-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.biometric_enrollment.fingerprint_enabled', true)
            ->assertJsonPath('data.biometric_enrollment.face_enabled', false)
            ->assertJsonPath('data.biometric_enrollment.pending_request', false);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/employee-info', [
                'identifier' => 'EMP-002',
            ])
            ->assertOk()
            ->assertJsonPath('data.biometric_enrollment.fingerprint_enabled', false)
            ->assertJsonPath('data.biometric_enrollment.face_enabled', false)
            ->assertJsonPath('data.biometric_enrollment.pending_request', true)
            ->assertJsonPath('data.biometric_enrollment.latest_request_status', 'pending');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/employee-info', [
                'identifier' => 'EMP-003',
            ])
            ->assertOk()
            ->assertJsonPath('data.biometric_enrollment.fingerprint_enabled', false)
            ->assertJsonPath('data.biometric_enrollment.face_enabled', false)
            ->assertJsonPath('data.biometric_enrollment.pending_request', false)
            ->assertJsonPath('data.biometric_enrollment.latest_request_status', null);
    }
}

