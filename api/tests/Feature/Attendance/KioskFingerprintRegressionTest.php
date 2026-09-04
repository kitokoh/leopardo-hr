<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QLT-002 (#6776) — non-régression du pointage kiosque par empreinte.
 *
 * Le flux historique (badge/carte + empreinte + validation manager) reste
 * vert après les lots ATT-004 (#6769) / BIO-006 (#6767) / BIO-007 (#6772) :
 *   - méthode `fingerprint` persistée telle quelle ;
 *   - badge accepté SANS flag biométrique employé (relaxation BIO-006),
 *     méthode `card` persistée ;
 *   - validation manager persistée (`manager`) ;
 *   - double check_in sans `device_event_id` → un seul log (422
 *     ALREADY_CHECKED_IN, aucun doublon de session ouverte) ;
 *   - chaque pointage est audité (`biometric_audit_logs.kiosk.punch.recorded`)
 *     sans aucune donnée de gabarit.
 */
final class KioskFingerprintRegressionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $fingerprintEmployee;

    private Employee $badgeEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company Regression',
            'slug' => 'company-regression-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@qlt-regression.test',
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

        $this->fingerprintEmployee = $this->makeEmployee('karim@qlt-regression.test', 'employee');
        $this->fingerprintEmployee->forceFill([
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ])->save();

        // Employé badge-only : aucun flag biométrique (cas de la relaxation).
        $this->badgeEmployee = $this->makeEmployee('badge@qlt-regression.test', 'employee');
        $this->badgeEmployee->forceFill([
            'matricule' => 'EMP-BADGE',
            'badge_number' => 'BDG-999',
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
        ])->save();

        $this->manager = $this->makeEmployee('manager@qlt-regression.test', 'manager');
        $this->manager->forceFill(['manager_role' => 'principal'])->save();
    }

    public function test_fingerprint_punch_still_persists_the_fingerprint_method(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk(['fingerprint', 'badge', 'manager']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'method' => 'fingerprint',
            ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'fingerprint');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->fingerprintEmployee->id,
            'method' => 'fingerprint',
        ]);
    }

    public function test_badge_punch_without_biometric_flags_still_works_and_persists_card(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk(['badge', 'fingerprint']);

        // Employé SANS flag biométrique mais badge autorisé par la matrice →
        // pointage accepté (relaxation BIO-006), méthode `card` persistée.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'BDG-999',
                'action' => 'check_in',
                'method' => 'badge',
            ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'card');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->badgeEmployee->id,
            'method' => 'card',
        ]);
    }

    public function test_manager_validation_punch_persists_the_manager_method(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk(['fingerprint', 'manager']);

        // Pointage validé par le manager actif du tenant → méthode `manager`.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'method' => 'manager',
                'manager_employee_id' => $this->manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'manager');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->fingerprintEmployee->id,
            'method' => 'manager',
        ]);
    }

    public function test_double_unkeyed_check_in_never_creates_a_second_open_session(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk(['fingerprint', 'badge']);

        // Premier check_in (sans device_event_id) → session ouverte.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'method' => 'fingerprint',
            ])
            ->assertCreated();

        // Second check_in sans clé d'idempotence → refus domaine 422
        // ALREADY_CHECKED_IN (issue #2669 : jamais deux sessions ouvertes).
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'method' => 'fingerprint',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'ALREADY_CHECKED_IN');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->fingerprintEmployee->id,
            'check_out' => null,
            'method' => 'fingerprint',
        ]);
    }

    public function test_each_kiosk_punch_is_audited_without_any_template_data(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk(['fingerprint', 'badge']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'method' => 'fingerprint',
                'device_event_id' => 'dev-evt-regression-001',
            ])
            ->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');

        // L'audit biométrique du pointage existe (corrélation appareil).
        $this->assertDatabaseHas('biometric_audit_logs', [
            'company_id' => $this->company->id,
            'employee_id' => $this->fingerprintEmployee->id,
            'event' => 'kiosk.punch.recorded',
            'method' => 'fingerprint',
            'correlation_id' => 'dev-evt-regression-001',
        ]);

        // Rédaction stricte (BIO-008 #6773) : ni colonne, ni clé gabarit.
        $columns = Schema::getColumnListing('biometric_audit_logs');
        $this->assertNotContains('template', $columns);
        $this->assertNotContains('capture', $columns);

        $row = DB::table('biometric_audit_logs')
            ->where('company_id', $this->company->id)
            ->where('event', 'kiosk.punch.recorded')
            ->first();
        $this->assertNotNull($row);
        // `context` est nullable pour l'événement de pointage : aucune clé
        // gabarit/capture ne doit jamais y figurer.
        $context = json_decode((string) $row->context, true);
        if (! is_array($context)) {
            $context = [];
        }
        $this->assertArrayNotHasKey('template', $context);
        $this->assertArrayNotHasKey('capture', $context);
    }

    private function makeEmployee(string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Regression',
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

    /**
     * @param  list<string>  $methods
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(array $methods): array
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Regression',
                'biometric_mode' => 'fingerprint',
                'punch_methods' => $methods,
            ])
            ->assertCreated();

        $deviceCode = $response->json('data.device_code');
        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($deviceCode);
        $this->assertIsString($syncToken);

        return [$deviceCode, $syncToken];
    }
}
