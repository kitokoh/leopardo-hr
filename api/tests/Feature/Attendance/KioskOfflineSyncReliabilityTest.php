<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QLT-001 (#6775) — fiabilité de la synchronisation offline kiosque,
 * acceptance BIO-007 (#6772).
 *
 * Couvre le contrat offline borné et signé :
 *   - idempotence par `device_event_id` (sync ET pointage direct) — un même
 *     événement appareil rejoué ne crée jamais deux présences ;
 *   - enveloppe `device_state` signée (HMAC) : compteur acquitté avancé et
 *     visible sur `/sync-status` ; falsification → 422 SYNC_INTEGRITY_MISMATCH
 *     sans persistance ; rejeu de compteur → 409 SYNC_COUNTER_STALE ;
 *   - fenêtre d'ancienneté (`max_age_days`) : événement expiré isolé
 *     (EVENT_EXPIRED) sans faire échouer le batch ;
 *   - fidélité de méthode : badge/pin/carte/manager sans flag biométrique,
 *     méthode réellement utilisée persistée (`badge` → `card`, `face` →
 *     `face`) ;
 *   - rétro-compatibilité : batch hérité sans enveloppe et sans méthode
 *     (chemin `external_event_id` + `biometric_type`).
 */
final class KioskOfflineSyncReliabilityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company Sync',
            'slug' => 'company-sync-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@qlt-sync.test',
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

        $this->employee = $this->makeEmployee('karim@qlt-sync.test', 'employee');
        $this->employee->forceFill([
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ])->save();

        $this->manager = $this->makeEmployee('manager@qlt-sync.test', 'manager');
        $this->manager->forceFill(['manager_role' => 'principal'])->save();
    }

    public function test_replayed_device_event_id_across_two_syncs_creates_a_single_log(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);
        $deviceEventId = 'dev-evt-replay-001';

        $payload = [
            'events' => [[
                'identifier' => 'FP-001',
                'action' => 'check_in',
                'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                'device_event_id' => $deviceEventId,
                'biometric_type' => 'fingerprint',
            ]],
        ];

        $first = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', $payload)
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.skipped_count', 0);

        $logId = $first->json('data.processed_log_ids.0');
        $this->assertIsInt($logId);

        // Rejeu du même événement appareil dans un second batch : le serveur
        // retourne le log existant (importExternalPunch idempotent), aucun
        // doublon de présence.
        $second = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', $payload)
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.processed_log_ids.0', $logId);

        $this->assertSame($first->json('data.processed_log_ids'), $second->json('data.processed_log_ids'));

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'external_event_id' => $deviceEventId,
        ]);
    }

    public function test_double_online_punch_with_same_device_event_id_returns_the_same_log(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);
        $deviceEventId = 'dev-evt-punch-002';

        $body = [
            'identifier' => 'FP-001',
            'action' => 'check_in',
            'device_event_id' => $deviceEventId,
        ];

        $first = $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', $body)
            ->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');
        $logId = DB::table('attendance_logs')->where('employee_id', $this->employee->id)->value('id');

        // Rejeu du pointage direct : même log retourné (`replayed`), aucune
        // seconde présence.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', $body)
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $first->json('data.employee_id'))
            ->assertJsonPath('data.replayed', true);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertSame((int) $logId, (int) DB::table('attendance_logs')->where('employee_id', $this->employee->id)->value('id'));
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'external_event_id' => $deviceEventId,
        ]);
    }

    public function test_signed_batch_advances_acked_counter_visible_on_sync_status(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);

        $deviceState = $this->signedBatch($deviceCode, $syncToken, counter: 1, nonce: 'nonce-signed-001');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'device_state' => $deviceState,
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-signed-001',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.acked_event_counter', 1);

        // Le compteur acquitté est persisté sur le kiosque et publié par
        // `/sync-status`.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/sync-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.acked_event_counter', 1);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'external_event_id' => 'dev-evt-signed-001',
        ]);
    }

    public function test_forged_integrity_is_rejected_and_nothing_is_persisted(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);

        // Enveloppe de forme valide mais HMAC falsifié.
        $deviceState = $this->signedBatch($deviceCode, $syncToken, counter: 1, nonce: 'nonce-forged-001');
        $deviceState['integrity'] = str_repeat('f', 64);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'device_state' => $deviceState,
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-forged-001',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SYNC_INTEGRITY_MISMATCH');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertSame(
            0,
            (int) DB::table('attendance_kiosks')->where('company_id', $this->company->id)->value('acked_event_counter')
        );
    }

    public function test_stale_counter_replay_is_rejected_with_409(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);

        $deviceState = $this->signedBatch($deviceCode, $syncToken, counter: 1, nonce: 'nonce-first-001');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'device_state' => $deviceState,
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-counter-001',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.acked_event_counter', 1);

        // Rejeu du batch déjà acquitté (même compteur) → 409, aucun doublon.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'device_state' => $deviceState,
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-counter-001',
                ]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'SYNC_COUNTER_STALE');

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_offline_event_older_than_max_age_days_is_skipped_with_event_expired(): void
    {
        // Fenêtre offline bornée à 1 jour pour ce test.
        config(['attendance.kiosk.offline.max_age_days' => 1]);

        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);
        $deviceState = $this->signedBatch($deviceCode, $syncToken, counter: 1, nonce: 'nonce-expired-001');

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'device_state' => $deviceState,
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subDays(2)->toIso8601String(),
                    'device_event_id' => 'dev-evt-expired-001',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.external_event_id', 'dev-evt-expired-001')
            ->assertJsonPath('data.skipped.0.reason', 'EVENT_EXPIRED')
            ->assertJsonPath('data.acked_event_counter', 1);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_sync_event_with_badge_method_is_processed_without_biometric_flags(): void
    {
        // Employé badge-only : aucun flag biométrique (relaxation BIO-006 —
        // badge/carte n'exige pas d'enrôlement biométrique).
        $badgeEmployee = $this->makeEmployee('badge@qlt-sync.test', 'employee');
        $badgeEmployee->forceFill([
            'matricule' => 'EMP-BADGE',
            'badge_number' => 'BDG-777',
            'biometric_fingerprint_enabled' => false,
            'biometric_face_enabled' => false,
        ])->save();

        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['badge', 'fingerprint']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [[
                    'identifier' => 'BDG-777',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-badge-001',
                    'method' => 'badge',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1);

        // La méthode RÉELLEMENT utilisée est persistée (badge → card).
        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $badgeEmployee->id,
            'method' => 'card',
            'external_event_id' => 'dev-evt-badge-001',
            'synced_from_offline' => true,
        ]);
    }

    public function test_offline_sync_preserves_face_method_fidelity(): void
    {
        // Employé avec visage enrôlé (flag posé comme après une activation).
        $this->employee->forceFill(['biometric_face_enabled' => true])->save();

        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager, ['face', 'fingerprint']);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => Carbon::now('UTC')->subMinutes(5)->toIso8601String(),
                    'device_event_id' => 'dev-evt-face-001',
                    'method' => 'face',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'method' => 'face',
            'external_event_id' => 'dev-evt-face-001',
        ]);
    }

    public function test_legacy_batch_without_device_state_and_methods_still_works(): void
    {
        [$deviceCode, $syncToken] = $this->registerKiosk($this->manager);

        // Contrat hérité (#3587) : ni enveloppe signée, ni méthode par
        // événement — le chemin `external_event_id` + `biometric_type` reste
        // fonctionnel.
        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/sync', [
                'events' => [[
                    'identifier' => 'FP-001',
                    'action' => 'check_in',
                    'occurred_at' => '2026-04-19T08:00:00Z',
                    'external_event_id' => 'evt-legacy-001',
                    'biometric_type' => 'fingerprint',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.skipped_count', 0)
            // Pas d'enveloppe → aucun compteur acquitté.
            ->assertJsonPath('data.acked_event_counter', 0);

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'external_event_id' => 'evt-legacy-001',
            'method' => 'biometric',
            'synced_from_offline' => true,
        ]);
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    private function makeEmployee(string $email, string $role): Employee
    {
        $employee = new Employee([
            'first_name' => ucfirst($role),
            'last_name' => 'Sync',
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
    private function registerKiosk(Employee $manager, array $methods = ['fingerprint']): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree Sync',
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

    /**
     * Construit l'enveloppe `device_state` signée (BIO-007) — même format
     * canonique que KioskOfflineSyncGuard : HMAC-SHA256 hex de
     * "DEVICE_CODE\ncounter\nnonce\nsigned_at" avec la clé = sync_token en
     * clair (header X-Kiosk-Token).
     *
     * @return array{counter: int, nonce: string, signed_at: string, integrity: string}
     */
    private function signedBatch(string $deviceCode, string $syncToken, int $counter, string $nonce): array
    {
        $signedAt = Carbon::now('UTC')->subSeconds(30)->toIso8601String();

        return [
            'counter' => $counter,
            'nonce' => $nonce,
            'signed_at' => $signedAt,
            'integrity' => hash_hmac(
                'sha256',
                implode("\n", [
                    strtoupper($deviceCode),
                    (string) $counter,
                    $nonce,
                    $signedAt,
                ]),
                $syncToken,
            ),
        ];
    }
}
