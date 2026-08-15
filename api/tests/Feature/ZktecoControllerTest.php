<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ZktecoControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_devices_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/zkteco/devices')
            ->assertUnauthorized();
    }

    public function test_devices_list_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/zkteco/devices')
            ->assertForbidden();
    }

    public function test_register_device_validates_required_fields(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [])
            ->assertUnprocessable();
    }

    public function test_heartbeat_accepts_valid_serial(): void
    {
        $this->postJson('/api/v1/zkteco/heartbeat/UNKNOWN-SERIAL')
            ->assertStatus(404);
    }

    public function test_sync_attendance_rejects_invalid_serial(): void
    {
        $this->postJson('/api/v1/zkteco/sync-attendance/NONEXISTENT', [
            'records' => [
                ['user_id' => '1', 'timestamp' => '2026-01-01 08:00:00'],
            ],
        ])->assertStatus(404);
    }

    public function test_sync_attendance_persists_punch_with_valid_enum_status(): void
    {
        // #2330 : 'status' => 'present' est invalide pour l'enum
        // attendance_logs.status (ontime/late/absent/leave/holiday/incomplete)
        // → 22P02, punch jamais persisté. Le statut doit être calculé
        // (ontime/late) comme AttendanceService::checkIn.
        // Le fixture MVP ne crée pas zkteco_sync_logs — table ajoutée ici
        // (miroir de la migration 2026_05_18_000003).
        Schema::create('zkteco_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zkteco_device_id')->constrained('zkteco_devices')->cascadeOnDelete();
            $table->enum('direction', ['pull', 'push'])->default('pull');
            $table->enum('sync_type', ['attendance', 'users', 'fingerprints', 'faces'])->default('attendance');
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['zkteco_device_id', 'created_at']);
        });

        $this->employee->update(['zkteco_id' => '42']);
        ZktecoDevice::query()->create([
            'company_id' => $this->company->id,
            'serial_number' => 'ZK-TEST-001',
            'name' => 'Portique QA',
        ]);

        $response = $this->postJson('/api/v1/zkteco/sync-attendance/ZK-TEST-001', [
            'records' => [
                [
                    'user_id' => '42',
                    'timestamp' => '2026-07-15 08:02:00',
                    'punch_type' => 0, // check_in
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.errors', 0);
        $response->assertJsonPath('data.records_processed', 1);

        $log = DB::table('attendance_logs')
            ->where('employee_id', $this->employee->id)
            ->where('date', '2026-07-15')
            ->first();

        $this->assertNotNull($log, 'le punch ZKTeco doit être persisté');
        $this->assertContains($log->status, ['ontime', 'late'], 'statut doit être un enum valide');
        $this->assertSame('zkteco', $log->method);
    }
}

