<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Infrastructure\Adapters\FakeFaceVerificationAdapter;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Infrastructure\Services\BiometricEnrollmentLifecycleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BIO-004 (#6765) — vérification faciale au pointage kiosque.
 *
 * Flux 1:1 (identification → capture → qualité/liveness/comparaison →
 * pointage), échec facial sans création de présence, nettoyage de la capture,
 * moteur scriptable (fake), défaut fail-closed.
 */
final class KioskFaceVerificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    private TenantManager $tenantManager;

    private BiometricEnrollmentLifecycleService $enrollments;

    private FakeFaceVerificationAdapter $faceAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);
        $this->enrollments = app(BiometricEnrollmentLifecycleService::class);

        // Instance scriptable unique pour tout le test (défaut : verified).
        $this->faceAdapter = new FakeFaceVerificationAdapter();
        $this->faceAdapter->setDefaultStatus(FaceVerificationStatus::Verified);
        $this->app->instance(FaceVerificationPort::class, $this->faceAdapter);

        $this->company = Company::query()->create([
            'name' => 'Company Face',
            'slug' => 'company-face-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@face-verify.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->employee = $this->makeEmployee('karim@face-verify.test', 'employee');
        $this->employee->forceFill([
            'matricule' => 'EMP-FACE',
            'zkteco_id' => 'FP-FACE',
            'biometric_face_enabled' => true,
            'biometric_fingerprint_enabled' => true,
        ])->save();

        $this->manager = $this->makeEmployee('manager@face-verify.test', 'manager');
        $this->manager->forceFill(['manager_role' => 'principal'])->save();
    }

    public function test_verified_face_creates_punch_and_cleans_capture(): void
    {
        $this->useFakeFaceAdapter(FaceVerificationStatus::Verified);
        $this->enrollActiveFaceTemplate();
        [$deviceCode, $syncToken] = $this->registerKiosk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/verify-face', [
                'identifier' => 'FP-FACE',
                'action' => 'check_in',
                'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
                'device_event_id' => 'dev-evt-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.method', 'face')
            ->assertJsonPath('data.correlation_id', 'dev-evt-001');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'method' => 'face',
        ]);

        // Capture temporaire supprimée après traitement.
        $this->assertSame([], Storage::disk('local')->files('biometric-captures/'.$this->company->id));
    }

    public function test_face_failures_never_create_attendance(): void
    {
        $scenarios = [
            FaceVerificationStatus::Rejected->value => 'VERIFICATION_REJECTED',
            FaceVerificationStatus::QualityFailed->value => 'VERIFICATION_QUALITY_FAILED',
            FaceVerificationStatus::LivenessFailed->value => 'VERIFICATION_LIVENESS_FAILED',
        ];

        foreach ($scenarios as $statusValue => $expectedCode) {
            $this->useFakeFaceAdapter(FaceVerificationStatus::from($statusValue));
            $this->enrollActiveFaceTemplate();
            [$deviceCode, $syncToken] = $this->registerKiosk();

            $this->withHeader('X-Kiosk-Token', $syncToken)
                ->postJson('/api/v1/kiosks/'.$deviceCode.'/verify-face', [
                    'identifier' => 'FP-FACE',
                    'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
                ])
                ->assertStatus(422)
                ->assertJsonPath('data.verified', false)
                ->assertJsonPath('data.reason_code', $expectedCode)
                ->assertJsonPath('data.fallback_methods', ['badge', 'pin']);

            DB::statement('SET search_path TO shared_tenants,public');
            $this->assertSame(
                0,
                DB::table('attendance_logs')->where('employee_id', $this->employee->id)->count(),
                "{$statusValue} ne doit créer aucune présence"
            );
        }
    }

    public function test_provider_unavailable_is_fail_closed_503(): void
    {
        // Défaut fail-closed : aucun fournisseur branché → le flux refuse.
        $this->app->instance(
            FaceVerificationPort::class,
            new \App\Core\AI\Infrastructure\Adapters\UnavailableFaceVerificationAdapter()
        );
        $this->enrollActiveFaceTemplate();
        [$deviceCode, $syncToken] = $this->registerKiosk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/verify-face', [
                'identifier' => 'FP-FACE',
                'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
            ])
            ->assertStatus(503)
            ->assertJsonPath('data.reason_code', 'FACE_PROVIDER_NOT_CONFIGURED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertSame(0, DB::table('attendance_logs')->count());
    }

    public function test_unenrolled_employee_is_rejected(): void
    {
        $this->useFakeFaceAdapter(FaceVerificationStatus::Verified);
        [$deviceCode, $syncToken] = $this->registerKiosk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/verify-face', [
                'identifier' => 'FP-FACE',
                'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'FACE_NOT_ENROLLED');
    }

    public function test_unknown_employee_is_rejected(): void
    {
        $this->useFakeFaceAdapter(FaceVerificationStatus::Verified);
        [$deviceCode, $syncToken] = $this->registerKiosk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/verify-face', [
                'identifier' => 'inconnu@face-verify.test',
                'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'EMPLOYEE_NOT_FOUND');
    }

    private function useFakeFaceAdapter(FaceVerificationStatus $default): void
    {
        $this->faceAdapter->setDefaultStatus($default);
    }

    private function enrollActiveFaceTemplate(): void
    {
        $this->tenantManager->withinTenant($this->company, function (): void {
            $enrollment = $this->enrollments->start(
                employee: $this->employee,
                method: VerificationMethod::Face,
                templatePayload: '{"provider":"fake","template":"FACE-BIN"}',
                provider: 'fake',
                actorEmployeeId: (int) $this->manager->id,
                correlationId: 'corr-enroll-face-'.Str::random(6),
            );
            $this->enrollments->activate($enrollment, (int) $this->manager->id);
        });
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(): array
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Face',
                'biometric_mode' => 'face',
                'punch_methods' => ['face', 'badge', 'pin'],
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }

    private function makeEmployee(string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Face',
            'email' => $email,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $this->company->id,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $employee;
    }
}
