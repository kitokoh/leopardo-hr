<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3587 — la sync kiosk offline ne doit plus skippe silencieusement.
 *
 * Avant : le serveur ignorait les identifiants inconnus / employés sans
 * biométrie (continue sans log) et le bridge marquait TOUT le batch synced
 * → pointages définitivement perdus, erreurs de paie invisibles.
 *
 * Désormais la réponse de sync détaille `skipped[]` (external_event_id,
 * identifier, reason) en plus de `processed_count`, et un check_out sans
 * session ouverte est un skip borné à l'événement (#3588), pas un 500 global.
 */
class KioskSyncSkippedEventsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_sync_reports_skipped_events_with_reasons(): void
    {
        [$manager, $employee, $nonBiometric] = $this->seedCompanyWithEmployees();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $response = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-ok-001',
                        'biometric_type' => 'fingerprint',
                    ],
                    [
                        'identifier' => 'UNKNOWN-PERSON',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:05:00Z',
                        'external_event_id' => 'evt-unknown-001',
                        'biometric_type' => 'fingerprint',
                    ],
                    [
                        'identifier' => 'NB-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:10:00Z',
                        'external_event_id' => 'evt-nobiometric-001',
                        'biometric_type' => 'fingerprint',
                    ],
                    [
                        // Espaces uniquement : passe la validation `required`
                        // mais est trimmé à vide côté service.
                        'identifier' => '   ',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:15:00Z',
                        'external_event_id' => 'evt-blank-001',
                        'biometric_type' => 'fingerprint',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.skipped_count', 3);

        $skipped = collect((array) $response->json('data.skipped'));
        $this->assertSame(
            'EMPLOYEE_NOT_FOUND',
            $skipped->firstWhere('external_event_id', 'evt-unknown-001')['reason'] ?? null
        );
        $this->assertSame(
            'BIOMETRIC_NOT_APPROVED',
            $skipped->firstWhere('external_event_id', 'evt-nobiometric-001')['reason'] ?? null
        );
        $this->assertSame(
            'IDENTIFIER_REQUIRED',
            $skipped->firstWhere('external_event_id', 'evt-blank-001')['reason'] ?? null
        );

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'external_event_id' => 'evt-ok-001',
        ]);
    }

    public function test_sync_check_out_without_open_session_is_a_bounded_skip_not_a_500(): void
    {
        [$manager] = $this->seedCompanyWithEmployees();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_out',
                        'occurred_at' => '2026-04-19T17:00:00Z',
                        'external_event_id' => 'evt-orphan-checkout-001',
                        'biometric_type' => 'fingerprint',
                    ],
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-checkin-after-001',
                        'biometric_type' => 'fingerprint',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.external_event_id', 'evt-orphan-checkout-001')
            ->assertJsonPath('data.skipped.0.reason', 'NO_OPEN_SESSION');
    }

    public function test_sync_without_skips_keeps_legacy_contract(): void
    {
        [$manager] = $this->seedCompanyWithEmployees();
        [$deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [
                    [
                        'identifier' => 'FP-001',
                        'action' => 'check_in',
                        'occurred_at' => '2026-04-19T08:00:00Z',
                        'external_event_id' => 'evt-clean-001',
                        'biometric_type' => 'fingerprint',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.skipped_count', 0)
            ->assertJsonCount(0, 'data.skipped');
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee} [manager, biometric employee, non-biometric employee]
     */
    private function seedCompanyWithEmployees(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Skip',
            'slug' => 'company-skip-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-skip.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@kiosk-skip.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $nonBiometric = new Employee([
            'first_name' => 'Sans',
            'last_name' => 'Biometrie',
            'email' => 'nobio@kiosk-skip.test',
            'matricule' => 'NB-001',
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
        ]);
        $nonBiometric->forceFill(['password_hash' => Hash::make('password123')])->save();
        $nonBiometric->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@kiosk-skip.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        return [$manager, $employee, $nonBiometric];
    }

    /**
     * @return array{0: string, 1: string} [device_code, sync_token]
     */
    private function registerKiosk(Employee $manager): array
    {
        $kioskResponse = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        return [
            $kioskResponse->json('data.device_code'),
            $kioskResponse->json('data.sync_token'),
        ];
    }
}
