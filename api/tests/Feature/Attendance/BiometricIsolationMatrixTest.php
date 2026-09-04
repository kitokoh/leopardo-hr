<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Domain\Enums\FaceVerificationStatus;
use App\Core\AI\Infrastructure\Adapters\FakeFaceVerificationAdapter;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QLT-001 (#6775) — matrice d'isolation cross-tenant du moteur biométrique.
 *
 * Deux tenants (A et B) avec employés, enrôlements et kiosques séparés :
 *   - A ne peut jamais lire/agir sur les enrôlements de B (status/activate/
 *     revoke → 404) ;
 *   - l'enrôlement ACTIF de B n'est pas utilisable pour pointer sur un
 *     kiosque de A (EMPLOYEE_NOT_FOUND sur verify-face, 404 sur punch) ;
 *   - une méthode absente de la matrice du kiosque est refusée même si
 *     l'employé porte le flag biométrique (PUNCH_METHOD_NOT_CONFIGURED) ;
 *   - un appareil révoqué est refusé partout (403 DEVICE_REVOKED) ;
 *   - les audits biométriques ne contiennent jamais de gabarit (BIO-008
 *     #6773) — ni colonne, ni clé de contexte.
 */
final class BiometricIsolationMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TEMPLATE_PLAINTEXT = '{"provider":"fake","template":"FACE-BIN-ISOLATION"}';

    protected function setUp(): void
    {
        parent::setUp();

        // Défaut : moteur facial scriptable (Verified) pour le flux verify-face.
        $faceAdapter = new FakeFaceVerificationAdapter();
        $faceAdapter->setDefaultStatus(FaceVerificationStatus::Verified);
        $this->app->instance(FaceVerificationPort::class, $faceAdapter);
    }

    public function test_tenant_a_cannot_read_or_act_on_tenant_b_enrollments(): void
    {
        $tenantA = $this->seedTenant('a', 'iso-a@qlt-matrix.test');
        $tenantB = $this->seedTenant('b', 'iso-b@qlt-matrix.test');

        // Tenant B : enrôlement face ACTIF (start + activate via l'API).
        [$kioskB] = $this->registerKiosk($tenantB['manager'], 'Kiosk B', ['face', 'fingerprint']);
        $bEnrollmentId = $this->startEnrollment($kioskB, $tenantB, 'idem-iso-b-start-001');
        $this->activateEnrollment($kioskB, $tenantB, $bEnrollmentId, 'idem-iso-b-activate-001');

        // Tenant A : kiosque configuré face+empreinte.
        [$kioskA] = $this->registerKiosk($tenantA['manager'], 'Kiosk A', ['face', 'fingerprint']);

        // A ne peut pas lire le statut d'enrôlement de l'employé B.
        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->getJson('/api/v1/kiosks/'.$kioskA['device_code'].'/enrollments/status?identifier='.rawurlencode($tenantB['employee']->zkteco_id ?? ''))
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        // A ne peut ni activer ni révoquer l'enrôlement de B (404, fail-closed).
        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/enrollments/'.$bEnrollmentId.'/activate', [
                'manager_employee_id' => $tenantA['manager']->id,
            ], ['Idempotency-Key' => 'idem-iso-a-activate-001'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/enrollments/'.$bEnrollmentId.'/revoke', [
                'manager_employee_id' => $tenantA['manager']->id,
            ], ['Idempotency-Key' => 'idem-iso-a-revoke-001'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND');

        DB::statement('SET search_path TO shared_tenants,public');

        // L'enrôlement de B est intact et toujours actif (contrôle positif).
        $this->assertDatabaseHas('biometric_enrollments', [
            'id' => $bEnrollmentId,
            'company_id' => $tenantB['company']->id,
            'status' => 'active',
        ]);
    }

    public function test_tenant_b_active_enrollment_is_not_usable_when_punching_on_tenant_a_kiosk(): void
    {
        $tenantA = $this->seedTenant('a', 'iso-punch-a@qlt-matrix.test');
        $tenantB = $this->seedTenant('b', 'iso-punch-b@qlt-matrix.test');

        // B possède un enrôlement face ACTIF + flag employé posé.
        [$kioskB] = $this->registerKiosk($tenantB['manager'], 'Kiosk B', ['face', 'fingerprint']);
        $bEnrollmentId = $this->startEnrollment($kioskB, $tenantB, 'idem-iso-punch-b-001');
        $this->activateEnrollment($kioskB, $tenantB, $bEnrollmentId, 'idem-iso-punch-b-002');
        $this->assertSame(1, $this->faceFlagFor($tenantB['employee']), 'le flag face de B doit être actif');

        // Kiosque A (face+fingerprint) : tentative de pointage de l'employé B.
        [$kioskA] = $this->registerKiosk($tenantA['manager'], 'Kiosk A', ['face', 'fingerprint']);

        // 1) Pointage direct method=face → l'employé B est hors du tenant A :
        // résolution scopée tenant → 404 (aucun log créé).
        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/punch', [
                'identifier' => $tenantB['employee']->zkteco_id,
                'action' => 'check_in',
                'method' => 'face',
            ])
            ->assertNotFound();

        // 2) Flux vérification faciale (verify-face) : code exact observé —
        // EMPLOYEE_NOT_FOUND (422 structuré, jamais de présence créée).
        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/verify-face', [
                'identifier' => $tenantB['employee']->zkteco_id,
                'capture' => UploadedFile::fake()->create('face.jpg', 20, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'EMPLOYEE_NOT_FOUND');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => $tenantB['employee']->id,
        ]);
    }

    public function test_disabled_method_is_refused_even_if_employee_flag_is_enabled(): void
    {
        $tenantA = $this->seedTenant('a', 'iso-matrix-a@qlt-matrix.test');

        // L'employé A a le flag face ACTIF (enrôlement activé via l'API)…
        [$kioskFace] = $this->registerKiosk($tenantA['manager'], 'Kiosk Face A', ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($kioskFace, $tenantA, 'idem-iso-matrix-start-001');
        $this->activateEnrollment($kioskFace, $tenantA, $enrollmentId, 'idem-iso-matrix-activate-001');
        $this->assertSame(1, $this->faceFlagFor($tenantA['employee']));

        // … mais la matrice de CE kiosque n'active pas `face` (empreinte +
        // badge uniquement) → la méthode est refusée côté serveur (BIO-006).
        [$kioskLimited] = $this->registerKiosk($tenantA['manager'], 'Kiosk Limited A', ['fingerprint', 'badge']);

        $this->withHeader('X-Kiosk-Token', $kioskLimited['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskLimited['device_code'].'/punch', [
                'identifier' => $tenantA['employee']->zkteco_id,
                'action' => 'check_in',
                'method' => 'face',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'PUNCH_METHOD_NOT_CONFIGURED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_revoked_kiosk_is_refused_on_every_surface(): void
    {
        $tenantA = $this->seedTenant('a', 'iso-revoked@qlt-matrix.test');
        [$kioskA] = $this->registerKiosk($tenantA['manager'], 'Kiosk A', ['face', 'fingerprint']);

        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = AttendanceKiosk::query()->where('company_id', $tenantA['company']->id)->firstOrFail()->id;
        $this->actingAs($tenantA['manager'], 'sanctum')
            ->postJson("/api/v1/kiosks/{$kioskId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $headers = ['X-Kiosk-Token' => $kioskA['sync_token']];

        // Pointage (auth controller).
        $this->withHeaders($headers)
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/punch', [
                'identifier' => $tenantA['employee']->zkteco_id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        // Sync offline (auth controller).
        $this->withHeaders($headers)
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/sync', [
                'events' => [[
                    'identifier' => $tenantA['employee']->zkteco_id,
                    'action' => 'check_in',
                    'external_event_id' => 'evt-revoked-matrix-001',
                ]],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        // Sync-status (middleware kiosk.device).
        $this->withHeaders($headers)
            ->getJson('/api/v1/kiosks/'.$kioskA['device_code'].'/sync-status')
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        // Statut d'enrôlement (middleware kiosk.device).
        $this->withHeaders($headers)
            ->getJson('/api/v1/kiosks/'.$kioskA['device_code'].'/enrollments/status?identifier='.rawurlencode((string) $tenantA['employee']->zkteco_id))
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        // Démarrage d'enrôlement (middleware kiosk.device → idempotency).
        $this->withHeaders($headers)
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/enrollments', [
                'identifier' => $tenantA['employee']->zkteco_id,
                'method' => 'face',
                'template_payload' => self::TEMPLATE_PLAINTEXT,
                'provider' => 'fake',
            ], ['Idempotency-Key' => 'idem-iso-revoked-001'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'DEVICE_REVOKED');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseCount('biometric_enrollments', 0);
    }

    public function test_biometric_audit_logs_never_contain_template_payloads(): void
    {
        $tenantA = $this->seedTenant('a', 'iso-audit@qlt-matrix.test');

        // Cycle complet tenant A : start → activate → pointage face.
        [$kioskA] = $this->registerKiosk($tenantA['manager'], 'Kiosk A', ['face', 'fingerprint']);
        $enrollmentId = $this->startEnrollment($kioskA, $tenantA, 'idem-iso-audit-start-001');
        $this->activateEnrollment($kioskA, $tenantA, $enrollmentId, 'idem-iso-audit-activate-001');

        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/punch', [
                'identifier' => $tenantA['employee']->zkteco_id,
                'action' => 'check_in',
                'method' => 'face',
                'device_event_id' => 'dev-evt-audit-matrix-001',
            ])
            ->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');

        // 1) Schéma : pas de colonne gabarit/capture sur la table d'audit.
        $columns = Schema::getColumnListing('biometric_audit_logs');
        $this->assertNotContains('template', $columns);
        $this->assertNotContains('capture', $columns);

        // 2) Aucune ligne d'audit biométrique ne porte de gabarit en clair.
        $rows = DB::table('biometric_audit_logs')->where('company_id', $tenantA['company']->id)->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            // `context` est nullable (ex. événement kiosk.punch.recorded) :
            // une valeur vide est traitée comme un contexte sans contenu.
            $context = json_decode((string) $row->context, true);
            if (! is_array($context)) {
                $context = [];
            }
            $this->assertArrayNotHasKey('template', $context);
            $this->assertArrayNotHasKey('capture', $context);
            $this->assertStringNotContainsString(
                'FACE-BIN-ISOLATION',
                (string) json_encode($row),
                'le gabarit ne doit jamais apparaître dans une ligne d\'audit biométrique'
            );
        }

        // 3) audit_logs (métadonnées structurées) : même rédaction stricte.
        $auditRows = DB::table('audit_logs')
            ->where('company_id', $tenantA['company']->id)
            ->where('action', 'like', 'biometric.enrollment.%')
            ->get();
        $this->assertNotEmpty($auditRows);
        foreach ($auditRows as $auditRow) {
            $metadata = json_decode((string) $auditRow->metadata, true);
            if (! is_array($metadata)) {
                $metadata = [];
            }
            $this->assertArrayNotHasKey('template', $metadata);
            $this->assertArrayNotHasKey('capture', $metadata);
        }

        // 4) L'événement de pointage est bien audité (kiosk.punch.recorded).
        $this->assertDatabaseHas('biometric_audit_logs', [
            'company_id' => $tenantA['company']->id,
            'employee_id' => $tenantA['employee']->id,
            'event' => 'kiosk.punch.recorded',
            'correlation_id' => 'dev-evt-audit-matrix-001',
        ]);
    }

    /**
     * @return array{company: Company, manager: Employee, employee: Employee}
     */
    private function seedTenant(string $suffix, string $emailDomain): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.strtoupper($suffix),
            'slug' => 'company-matrix-'.$suffix.'-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $emailDomain,
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

        $manager = $this->makeEmployee($company, 'manager-'.$suffix.'@qlt-matrix.test', 'manager');
        $manager->forceFill(['manager_role' => 'principal'])->save();

        $employee = $this->makeEmployee($company, 'karim-'.$suffix.'@qlt-matrix.test', 'employee');
        $employee->forceFill([
            'matricule' => 'EMP-'.strtoupper($suffix),
            'zkteco_id' => 'FP-'.strtoupper($suffix),
            'badge_number' => 'BDG-'.strtoupper($suffix),
        ])->save();

        return ['company' => $company, 'manager' => $manager, 'employee' => $employee];
    }

    private function makeEmployee(Company $company, string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Matrix',
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

    /**
     * @param  list<string>  $methods
     * @return array{0: array{device_code: string, sync_token: string}}
     */
    private function registerKiosk(Employee $manager, string $name, array $methods): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => $name,
                'biometric_mode' => 'face',
                'punch_methods' => $methods,
            ])
            ->assertCreated();

        return [[
            'device_code' => $response->json('data.device_code'),
            'sync_token' => $response->json('data.sync_token'),
        ]];
    }

    /**
     * @param  array{company: Company, manager: Employee, employee: Employee}  $tenant
     */
    private function startEnrollment(array $kiosk, array $tenant, string $idempotencyKey): int
    {
        $response = $this->withHeader('X-Kiosk-Token', $kiosk['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kiosk['device_code'].'/enrollments', [
                'identifier' => $tenant['employee']->zkteco_id,
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
     * @param  array{company: Company, manager: Employee, employee: Employee}  $tenant
     */
    private function activateEnrollment(array $kiosk, array $tenant, int $enrollmentId, string $idempotencyKey): void
    {
        $this->withHeader('X-Kiosk-Token', $kiosk['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kiosk['device_code'].'/enrollments/'.$enrollmentId.'/activate', [
                'manager_employee_id' => $tenant['manager']->id,
            ], ['Idempotency-Key' => $idempotencyKey])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    private function faceFlagFor(Employee $employee): int
    {
        DB::statement('SET search_path TO shared_tenants,public');

        return (int) DB::table('employees')->where('id', $employee->id)->value('biometric_face_enabled');
    }
}
