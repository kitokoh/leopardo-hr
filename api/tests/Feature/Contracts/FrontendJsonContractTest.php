<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use App\Models\KioskAnnouncement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FrontendJsonContractTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->ensureKioskContractTables();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_admin_dashboard_summary_payload_matches_frontend_contract(): void
    {
        [$company, $manager] = $this->managerActor();
        Employee::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'active']);
        Employee::factory()->archived()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'employees_total',
                    'employees_active',
                    'departments',
                    'today_attendance',
                    'pending_absences',
                ],
            ]);

        $this->assertSame(4, (int) $response->json('data.employees_total'));
        $this->assertSame(3, (int) $response->json('data.employees_active'));
    }

    public function test_admin_export_employees_payload_matches_frontend_contract(): void
    {
        [$company, $manager] = $this->managerActor();
        Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Kaci',
            'email' => 'nadia.kaci@example.test',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/export/employees?format=json');

        $response->assertOk()
            ->assertJsonPath('data.format', 'json')
            ->assertJsonStructure([
                'data' => [
                    'format',
                    'count',
                    'records' => [
                        '*' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'status',
                            'contract_start',
                        ],
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.count'));
    }

    public function test_api_error_payloads_are_stable_for_frontends(): void
    {
        [, $manager] = $this->managerActor();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/kiosks', [])
            ->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'localized_message',
                'errors' => [
                    'name',
                ],
            ]);

        $this->getJson('/api/v1/employees/999999')
            ->assertNotFound()
            ->assertJsonStructure([
                'error',
                'message',
                'localized_message',
            ])
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');
    }

    public function test_kiosk_token_only_roster_and_announcement_payloads_match_kiosk_contract(): void
    {
        [$company, , $employee] = $this->kioskActor();
        [$kiosk, $plainToken] = $this->kiosk($company);

        KioskAnnouncement::query()->create([
            'company_id' => $company->id,
            'title' => 'Maintenance',
            'body' => 'Pointage kiosque operationnel.',
            'priority' => 'high',
            'is_active' => true,
        ]);

        $roster = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/roster");

        $roster->assertOk()
            ->assertJsonPath('data.device_code', $kiosk->device_code)
            ->assertJsonPath('data.employees.0.employee_id', $employee->id)
            ->assertJsonStructure([
                'data' => [
                    'device_code',
                    'company_id',
                    'company_name',
                    'employees' => [
                        '*' => [
                            'employee_id',
                            'name',
                            'email',
                            'matricule',
                            'zkteco_id',
                            'face_enabled',
                            'fingerprint_enabled',
                        ],
                    ],
                ],
            ]);

        $announcements = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/announcements");

        $announcements->assertOk()
            ->assertJsonPath('data.0.title', 'Maintenance')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'body',
                        'priority',
                        'starts_at',
                        'expires_at',
                    ],
                ],
            ]);
    }

    public function test_kiosk_announcements_tolerate_legacy_partial_table(): void
    {
        [$company] = $this->kioskActor();
        [$kiosk, $plainToken] = $this->kiosk($company);

        Schema::dropIfExists('kiosk_announcements');
        Schema::create('kiosk_announcements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('title', 200);
            $table->text('body');
        });

        DB::table('kiosk_announcements')->insert([
            'company_id' => $company->id,
            'title' => 'Legacy notice',
            'body' => 'Annonce issue d un tenant historique.',
        ]);

        $this->withHeader('X-Kiosk-Token', $plainToken)
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/announcements")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Legacy notice')
            ->assertJsonPath('data.0.priority', 'normal')
            ->assertJsonPath('data.0.starts_at', null)
            ->assertJsonPath('data.0.expires_at', null);
    }

    public function test_kiosk_announcements_return_empty_when_legacy_table_cannot_be_tenant_scoped(): void
    {
        [$company] = $this->kioskActor();
        [$kiosk, $plainToken] = $this->kiosk($company);

        Schema::dropIfExists('kiosk_announcements');
        Schema::create('kiosk_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
        });

        DB::table('kiosk_announcements')->insert([
            'title' => 'Unscoped notice',
            'body' => 'Cette annonce ne doit pas fuiter sans company_id.',
        ]);

        $this->withHeader('X-Kiosk-Token', $plainToken)
            ->getJson("/api/v1/kiosks/{$kiosk->device_code}/announcements")
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_kiosk_token_only_employee_info_and_leave_balance_payloads_match_kiosk_contract(): void
    {
        [$company, , $employee] = $this->kioskActor();
        [$kiosk, $plainToken] = $this->kiosk($company);

        DB::table('leave_balances')->insert([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => 1,
            'balance' => 18,
            'used' => 4,
            'pending' => 2,
            'year' => now()->year,
            'updated_at' => now(),
        ]);

        $info = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->postJson("/api/v1/kiosks/{$kiosk->device_code}/employee-info", [
                'identifier' => $employee->matricule,
            ]);

        $info->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.leave_balances.0.remaining', 18)
            ->assertJsonPath('data.leave_balances.0.total', 24)
            ->assertJsonStructure([
                'data' => [
                    'employee' => [
                        'id',
                        'name',
                        'matricule',
                        'department',
                        'position',
                        'photo_url',
                    ],
                    'today_attendance',
                    'leave_balances' => [
                        '*' => [
                            'leave_type',
                            'total',
                            'used',
                            'pending',
                            'remaining',
                        ],
                    ],
                ],
            ]);

        $balance = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->postJson("/api/v1/kiosks/{$kiosk->device_code}/leave-balance", [
                'identifier' => $employee->matricule,
            ]);

        $balance->assertOk()
            ->assertJsonPath('data.employee_name', 'Amina Kiosk')
            ->assertJsonPath('data.balances.0.remaining', 18)
            ->assertJsonStructure([
                'data' => [
                    'employee_name',
                    'year',
                    'balances' => [
                        '*' => [
                            'leave_type',
                            'total',
                            'used',
                            'pending',
                            'remaining',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function managerActor(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $manager];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function kioskActor(): array
    {
        [$company, $manager] = $this->managerActor();
        $employee = Employee::factory()->withBiometric()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Kiosk',
            'email' => 'amina.kiosk@example.test',
            'matricule' => 'KIOSK-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_face_enabled' => false,
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    /**
     * @return array{0: AttendanceKiosk, 1: string}
     */
    private function kiosk(Company $company): array
    {
        $plainToken = 'kiosk-token-contract';
        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Hall principal',
            'location_label' => 'Accueil',
            'device_code' => 'KIOSKCONTRACT',
            'sync_token_hash' => Hash::make($plainToken),
            'status' => 'active',
            'biometric_mode' => 'fingerprint',
        ]);

        return [$kiosk, $plainToken];
    }

    private function ensureKioskContractTables(): void
    {
        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table): void {
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

        if (! Schema::hasTable('kiosk_announcements')) {
            Schema::create('kiosk_announcements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('body');
                $table->string('priority', 20)->default('normal');
                $table->boolean('is_active')->default(true);
                $table->timestampTz('starts_at')->nullable();
                $table->timestampTz('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
