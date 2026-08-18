<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4172 (audit 2026-08-16) : le POST web /kiosk/{deviceCode}/punch exige une
 * session qui a réellement chargé la page kiosk — un script qui ne possède
 * que le device_code (logs, QR, provisioning) ne peut plus forger des
 * pointages en masse. Le device_code seul ne suffit pas : la session est
 * posée par la route show, pas par la simple connaissance du code.
 */
class WebKioskSessionGateTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La route web est protégée par CSRF ; le test du gate de session
        // n'a pas vocation à re-tester le CSRF (couvert par ailleurs).
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * @return array{0: string, 1: string} [deviceCode, syncToken]
     *
     * Insère le kiosque directement en base : le test du gate de session n'a
     * pas vocation à passer par POST /api/v1/kiosks (Sentry context no-op en
     * CI mais actif localement, cf. SentryContextMiddleware).
     */
    private function seedKioskViaApi(): array
    {
        $company = Company::query()->create([
            'name' => 'Company Kiosk',
            'slug' => 'company-kiosk-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@kiosk-gate.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        // #3677 : company_id/role/status ne sont plus fillables → forceCreate
        // (le create() mass-assignment les abandonnerait silencieusement).
        $employee = Employee::query()->forceCreate([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@kiosk-gate.test',
            'matricule' => 'EMP-001',
            'zkteco_id' => 'FP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'FP-001',
        ]);

        $manager = Employee::query()->forceCreate([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => 'manager@kiosk-gate.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO public');

        $syncToken = Str::random(48);
        $deviceCode = strtoupper(Str::random(10));

        DB::statement('SET search_path TO shared_tenants,public');
        DB::table('attendance_kiosks')->insert([
            'company_id' => $company->id,
            'name' => 'Borne accueil',
            'device_code' => $deviceCode,
            'sync_token_hash' => Hash::make($syncToken),
            'biometric_mode' => 'fingerprint',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('SET search_path TO public');

        return [$deviceCode, $syncToken];
    }

    public function test_web_punch_without_kiosk_page_session_is_rejected(): void
    {
        [$deviceCode] = $this->seedKioskViaApi();

        // POST direct (curl-like) : pas de session show → 403.
        // (Le refus est tracé sur le canal audit par le contrôleur — vérifié
        // par le statut 403 et l'absence de pointage ci-dessous.)
        $response = $this->post('/kiosk/'.$deviceCode.'/punch', [
            'identifier' => 'EMP-001',
            'action' => 'check_in',
        ]);

        $response->assertForbidden();

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => Employee::query()->where('matricule', 'EMP-001')->value('id'),
        ]);

    }

    public function test_web_punch_after_loading_kiosk_page_is_accepted(): void
    {
        [$deviceCode] = $this->seedKioskViaApi();

        // 1. Charger la page kiosk (pose la session).
        $this->get('/kiosk/'.$deviceCode)->assertOk();

        // 2. Le POST depuis cette session est accepté.
        $this->post('/kiosk/'.$deviceCode.'/punch', [
            'identifier' => 'EMP-001',
            'action' => 'check_in',
        ])->assertRedirect();

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => Employee::query()->where('matricule', 'EMP-001')->value('id'),
        ]);
    }
}
