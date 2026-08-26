<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — le `device_code` kiosque ne doit
 * plus être stocké en clair : hash déterministe (sha256 hex du code en
 * majuscules) au repos, code en clair retourné uniquement à la création
 * (provisioning), lookup par hachage de l'entrée.
 */
class KioskDeviceCodeAtRestTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_register_returns_plaintext_code_but_stores_sha256_hash(): void
    {
        [$manager] = $this->seedCompanyManager();

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        $plainCode = $response->json('data.device_code');
        $this->assertIsString($plainCode);
        // Le code en clair est un identifiant court alphanumérique majuscule.
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $plainCode);

        $syncToken = $response->json('data.sync_token');
        $this->assertIsString($syncToken);

        // En base : digest sha256 hex (64 caractères), jamais le code en clair.
        // (search_path posé explicitement : après la requête HTTP, la session
        // n'est plus dans le schéma du tenant — cf. test legacy ci-dessous.)
        DB::statement('SET search_path TO shared_tenants,public');
        $kioskId = $response->json('data.id');
        $stored = DB::table('attendance_kiosks')->where('id', $kioskId)->value('device_code');
        $this->assertIsString($stored);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored);
        $this->assertNotSame($plainCode, $stored);
        $this->assertSame(AttendanceKiosk::hashDeviceCode($plainCode), $stored);
    }

    public function test_roster_lookup_works_with_plaintext_code_against_stored_hash(): void
    {
        [$manager] = $this->seedCompanyManager();
        // registerKiosk retourne [manager, device_code, sync_token] — la
        // 1re position est l'Employee (ne pas la dépaqueter dans deviceCode).
        [, $deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->getJson('/api/v1/kiosks/'.$deviceCode.'/roster')
            ->assertOk()
            ->assertJsonPath('data.device_code', $deviceCode);
    }

    public function test_punch_lookup_works_with_plaintext_code_against_stored_hash(): void
    {
        [$manager] = $this->seedCompanyManager();
        // registerKiosk retourne [manager, device_code, sync_token] — la
        // 1re position est l'Employee (ne pas la dépaqueter dans deviceCode).
        [, $deviceCode, $syncToken] = $this->registerKiosk($manager);

        $this->withHeader('X-Kiosk-Token', $syncToken)
            ->postJson('/api/v1/kiosks/'.$deviceCode.'/punch', [
                'identifier' => 'EMP-001',
                'action' => 'check_in',
            ])
            ->assertCreated();
    }

    public function test_legacy_plaintext_row_is_not_resolved_after_hashing_contract(): void
    {
        // Après la migration de backfill, toute ligne dont le device_code
        // n'est pas un digest 64-hex est hors contrat : le lookup hash
        // l'entrée, une ligne en clair ne peut plus être résolue (c'est le
        // comportement voulu — les codes en clair n'existent plus en base).
        [$manager] = $this->seedCompanyManager();

        $companyId = DB::table('companies')->value('id');
        DB::statement('SET search_path TO shared_tenants,public');
        DB::table('attendance_kiosks')->insert([
            'company_id' => $companyId,
            'name' => 'Legacy borne',
            'device_code' => 'LEGACYCODE1',
            'sync_token_hash' => Hash::make('token'),
            'biometric_mode' => 'fingerprint',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('SET search_path TO public');

        $this->withHeader('X-Kiosk-Token', 'token')
            ->getJson('/api/v1/kiosks/LEGACYCODE1/roster')
            ->assertNotFound();
    }

    /**
     * @return array{0: Employee, 1: string, 2: string} [manager, device_code, sync_token]
     */
    private function registerKiosk(Employee $manager): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => 'Entree principale',
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        return [
            $manager,
            (string) $response->json('data.device_code'),
            (string) $response->json('data.sync_token'),
        ];
    }

    /**
     * @return array{0: Employee}
     */
    private function seedCompanyManager(): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-hash.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        // Employé biométrique requis par le punch kiosque (sinon 404
        // ModelNotFoundException sur l'identifiant — cf. KioskMultiEventPunchTest).
        $employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@kiosk-hash.test',
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

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@kiosk-hash.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        DB::statement('SET search_path TO public');

        return [$manager];
    }
}
