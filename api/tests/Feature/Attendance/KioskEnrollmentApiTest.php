<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QLT-001 (#6775) — surface API d'enrôlement kiosque, acceptance ATT-004
 * (#6769).
 *
 * Couvre : démarrage pending avec gabarit chiffré (réponse neutre, jamais de
 * `template`) ; Idempotency-Key OBLIGATOIRE sur les écritures
 * (MISSING_IDEMPOTENCY_KEY) et rejeu 24 h à l'identique (même enrollment_id,
 * header `Idempotent-Replayed: true`) ; activation manager (flag employé
 * posé) ; non-manager refusé ; révocation (flag retiré) ; statut neutre par
 * méthode ; isolation cross-tenant (404) ; matrice de méthodes du kiosque
 * (PUNCH_METHOD_NOT_CONFIGURED) ; appareil révoqué (DEVICE_REVOKED).
 */
final class KioskEnrollmentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TEMPLATE_PLAINTEXT = '{"provider":"fake","template":"FACE-BIN-v3"}';

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company Enrollment',
            'slug' => 'company-enrollment-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@qlt-enrollment.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->employee = $this->makeEmployee($this->company, 'karim@qlt-enrollment.test', 'employee');
        $this->employee->forceFill([
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
        ])->save();

        $this->manager = $this->makeEmployee($this->company, 'manager@qlt-enrollment.test', 'manager');
        $this->manager->forceFill(['manager_role' => 'principal'])->save();
    }

    public function test_start_creates_pending_enrollment_with_encrypted_template_and_neutral_response(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        $response = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', [
                'identifier' => 'FP-001',
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
                'correlation_id' => 'corr-start-api-001',
            ], ['Idempotency-Key' => 'idem-start-001'])
            ->assertCreated()
            ->assertJsonPath('data.method', 'face')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.enrolled_via', 'kiosk');

        // Réponse NEUTRE : le gabarit ne doit jamais être sérialisé (BIO-003).
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('template', $data);
        $this->assertArrayNotHasKey('template_payload', $data);
        $this->assertArrayNotHasKey('capture', $data);

        $enrollmentId = $data['enrollment_id'];

        DB::statement('SET search_path TO shared_tenants,public');

        // Ligne pending, gabarit chiffré au repos (jamais le clair).
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $enrollmentId,
            'employee_id' => $this->employee->id,
            'method' => 'face',
            'status' => 'pending',
            'version' => 1,
        ]);
        $rawTemplate = DB::table('biometric_enrollments')->where('id', $enrollmentId)->value('template');
        $this->assertIsString($rawTemplate);
        $this->assertNotSame(self::TEMPLATE_PLAINTEXT, $rawTemplate);
        $this->assertStringNotContainsString('FACE-BIN-v3', $rawTemplate);
    }

    public function test_start_without_idempotency_key_is_rejected_with_422(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', [
                'identifier' => 'FP-001',
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'MISSING_IDEMPOTENCY_KEY');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('biometric_enrollments', 0);
    }

    public function test_start_replayed_with_same_idempotency_key_returns_the_same_enrollment(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        $body = [
            'identifier' => 'FP-001',
            'method' => 'face',
            'template_payload' => self::TEMPLATE_PLAINTEXT,
            'provider' => 'fake',
        ];
        $headers = ['Idempotency-Key' => 'idem-start-replay-002'];

        $first = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', $body, $headers)
            ->assertCreated();
        $firstEnrollmentId = $first->json('data.enrollment_id');

        // Rejeu à l'identique (même clé + même corps) dans les 24 h : la
        // réponse mémorisée est rejouée avec le même enrollment_id.
        $replayed = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', $body, $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.enrollment_id', $firstEnrollmentId);

        $this->assertSame($first->json('data'), $replayed->json('data'));

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('biometric_enrollments', 1);
    }

    public function test_activate_sets_enrollment_active_and_enables_employee_face_flag(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($deviceCode, $syncToken, 'idem-activate-001');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/'.$enrollmentId.'/activate', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-activate-002'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.enrollment_id', $enrollmentId);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $enrollmentId,
            'status' => 'active',
        ]);
        // L'activation rend la méthode réellement utilisable (flag employé).
        $this->assertSame(
            1,
            (int) DB::table('employees')->where('id', $this->employee->id)->value('biometric_face_enabled')
        );
    }

    public function test_activate_with_non_manager_employee_is_rejected_with_403(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($deviceCode, $syncToken, 'idem-activate-reject-001');

        // Un simple employé (non manager) ne peut pas valider une activation.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/'.$enrollmentId.'/activate', [
                'manager_employee_id' => $this->employee->id,
            ], ['Idempotency-Key' => 'idem-activate-reject-002'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_VALIDATION_REQUIRED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $enrollmentId,
            'status' => 'pending',
        ]);
    }

    public function test_revoke_clears_the_employee_biometric_flag_when_no_other_active_enrollment(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($deviceCode, $syncToken, 'idem-revoke-001');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/'.$enrollmentId.'/activate', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-revoke-002'])
            ->assertOk();

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/'.$enrollmentId.'/revoke', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-revoke-003'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $enrollmentId,
            'status' => 'revoked',
        ]);
        $this->assertNotNull(DB::table('biometric_enrollments')->where('id', $enrollmentId)->value('revoked_at'));

        // Plus aucun actif → le flag employé est retiré (matrice BIO-006).
        $this->assertSame(
            0,
            (int) DB::table('employees')->where('id', $this->employee->id)->value('biometric_face_enabled')
        );
    }

    public function test_status_endpoint_returns_neutral_per_method_statuses_without_template(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($deviceCode, $syncToken, 'idem-status-001');

        // pending face → le statut expose pending, jamais le gabarit.
        $pending = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/status?identifier='.rawurlencode('FP-001'))
            ->assertOk()
            ->assertJsonPath('data.employee_id', $this->employee->id);

        $enrollments = $pending->json('data.enrollments');
        $this->assertIsArray($enrollments);
        $this->assertSame('face', $enrollments[0]['method'] ?? null);
        $this->assertSame('pending', $enrollments[0]['status'] ?? null);
        $this->assertSame('none', $enrollments[1]['status'] ?? null);

        // Activation → statut actif reflété.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/'.$enrollmentId.'/activate', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-status-002'])
            ->assertOk();

        $active = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/enrollments/status?identifier='.rawurlencode('FP-001'))
            ->assertOk();
        $activeEnrollments = $active->json('data.enrollments');
        $this->assertIsArray($activeEnrollments);
        $this->assertSame('active', $activeEnrollments[0]['status'] ?? null);

        // Neutralité stricte : aucune clé gabarit/capture dans la réponse.
        $this->assertArrayNotHasKey('template', (array) $active->json('data'));
        foreach ($activeEnrollments as $enrollment) {
            $this->assertIsArray($enrollment);
            $this->assertArrayNotHasKey('template', $enrollment);
            $this->assertArrayNotHasKey('capture', $enrollment);
        }
    }

    public function test_kiosk_of_tenant_a_cannot_act_on_tenant_b_enrollment(): void
    {
        // Tenant B : employé + kiosque + enrôlement pending.
        $companyB = $this->createCompany('Company B', 'b@qlt-enrollment.test', 'company-b-enrollment');
        $managerB = $this->makeEmployee($companyB, 'manager-b@qlt-enrollment.test', 'manager');
        $managerB->forceFill(['manager_role' => 'principal'])->save();
        // L'employé B (zkteco FP-B) n'est résolu que dans le tenant B.
        $employeeB = $this->makeEmployee($companyB, 'karim-b@qlt-enrollment.test', 'employee');
        $employeeB->forceFill(['matricule' => 'EMP-B', 'zkteco_id' => 'FP-B'])->save();

        [$deviceCodeB, $syncTokenB] = $this->registerKiosk($managerB, ['face', 'fingerprint']);

        $bEnrollmentResponse = $this->withHeader('X-Kiosk-Token', $syncTokenB)
            ->postJson('/api/v1/kiosks/'.$deviceCodeB.'/enrollments', [
                'identifier' => $employeeB->zkteco_id,
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => 'idem-b-start-001'])
            ->assertCreated();
        $bEnrollmentId = $bEnrollmentResponse->json('data.enrollment_id');
        $this->assertIsInt($bEnrollmentId);

        // Le kiosque du tenant A ne peut NI activer, NI révoquer un
        // enrôlement du tenant B (404, aucun indice).
        [$deviceCodeA, $syncTokenA] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        $this->withHeader('X-Kiosk-Token', $syncTokenA)
            ->postJson('/api/v1/kiosks/'.$deviceCodeA.'/enrollments/'.$bEnrollmentId.'/activate', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-a-activate-b-001'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        $this->withHeader('X-Kiosk-Token', $syncTokenA)
            ->postJson('/api/v1/kiosks/'.$deviceCodeA.'/enrollments/'.$bEnrollmentId.'/revoke', [
                'manager_employee_id' => $this->manager->id,
            ], ['Idempotency-Key' => 'idem-a-revoke-b-001'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        // Tentative de pointage cross-tenant : l'employé B est inconnu du
        // kiosque A (résolution scopée tenant) → 404, aucune présence.
        $this->withHeader('X-Kiosk-Token', $syncTokenA)
            ->postJson('/api/v1/kiosks/'.$deviceCodeA.'/punch', [
                'identifier' => $employeeB->zkteco_id,
                'action' => 'check_in',
                'method' => 'face',
            ])
            ->assertNotFound();

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);

        // L'enrôlement du tenant B est intact (contrôle positif dans B).
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $bEnrollmentId,
            'company_id' => $companyB->id,
            'status' => 'pending',
        ]);
    }

    public function test_start_for_employee_of_another_tenant_returns_404(): void
    {
        $companyB = $this->createCompany('Company B', 'b2@qlt-enrollment.test', 'company-b-enrollment-2');
        $managerB = $this->makeEmployee($companyB, 'manager-b2@qlt-enrollment.test', 'manager');
        $managerB->forceFill(['manager_role' => 'principal'])->save();
        $employeeB = $this->makeEmployee($companyB, 'karim-b2@qlt-enrollment.test', 'employee');
        $employeeB->forceFill(['matricule' => 'EMP-B2', 'zkteco_id' => 'FP-B2'])->save();

        [$deviceCodeA, $syncTokenA] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        // Le kiosque A ne peut pas démarrer un enrôlement pour un employé du
        // tenant B : résolution scopée tenant → 404.
        $this->withHeader('X-Kiosk-Token', $syncTokenA)
            ->postJson('/api/v1/kiosks/'.$deviceCodeA.'/enrollments', [
                'identifier' => $employeeB->zkteco_id,
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => 'idem-a-start-b-001'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('biometric_enrollments', 0);
    }

    public function test_enrollment_method_not_allowed_by_kiosk_matrix_is_rejected(): void
    {
        // Kiosque configuré SANS face dans sa matrice (badge + empreinte).
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['badge', 'fingerprint']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', [
                'identifier' => 'FP-001',
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => 'idem-matrix-001'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PUNCH_METHOD_NOT_CONFIGURED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('biometric_enrollments', 0);
    }

    public function test_revoked_device_cannot_start_an_enrollment(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = AttendanceKiosk::query()->where('company_id', $this->company->id)->firstOrFail()->id;
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/kiosks/{$kioskId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        // Appareil révoqué → 403 DEVICE_REVOKED (avant même la validation
        // de l'Idempotency-Key, le middleware `kiosk.device` est externe).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', [
                'identifier' => 'FP-001',
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => 'idem-revoked-001'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('biometric_enrollments', 0);
    }

    /**
     * Démarre un enrôlement face via l'API et retourne son id.
     */
    private function startEnrollment(string $deviceCode, string $syncToken, string $idempotencyKey): int
    {
        $response = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/enrollments', [
                'identifier' => 'FP-001',
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => $idempotencyKey])
            ->assertCreated();

        $enrollmentId = $response->json('data.enrollment_id');
        $this->assertIsInt($enrollmentId);

        return $enrollmentId;
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(Employee $manager, array $methods): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Enrollment',
                'biometric_mode' => 'face',
                'punch_methods' => $methods,
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }

    private function makeEmployee(Company $company, string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Enrollment',
            'email' => $email,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function createCompany(string $name, string $email, string $slugPrefix): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => $slugPrefix.'-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => $email,
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        return $company;
    }
}
