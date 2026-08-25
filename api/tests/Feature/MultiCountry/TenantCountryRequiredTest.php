<?php

declare(strict_types=1);

namespace Tests\Feature\MultiCountry;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Platform\Infrastructure\Services\CompanyProvisioningService;
use App\Support\CountryDefaults;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1867) — pays légal obligatoire et verrouillé au niveau du
 * tenant : provisioning, registre des pays supportés, gardes RH/paie,
 * neutralisation du country_code client, simulation sans écriture.
 */
class TenantCountryRequiredTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    // ── Registre des pays supportés ────────────────────────────────────────

    public function test_supported_countries_registry_returns_payroll_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $response = $this->getJson('/api/v1/supported-countries')->assertOk();

        /** @var array<int, array{country: string, currency: string, timezone: string, language: string, available: bool, confidence: string, compliance: array{level: string, warning: string, warning_localized: string, source: string, verified_at: string|null}}> $registry */
        $registry = $response->json('data');
        $countries = collect($registry)->keyBy('country');

        /** @var array{country: string, currency: string, timezone: string, language: string, available: bool, confidence: string, compliance: array{level: string, warning: string, warning_localized: string, source: string, verified_at: string|null}}|null $dz */
        $dz = $countries->get('DZ');
        $this->assertNotNull($dz);
        $this->assertNotNull($countries->get('CI'));
        $this->assertSame('DZD', $dz['currency']);
        $this->assertSame('Africa/Algiers', $dz['timezone']);
        $this->assertSame('fr', $dz['language']);
        $this->assertTrue($dz['available']);
        $this->assertContains($dz['confidence'], ['pilot', 'placeholder', 'production', 'unknown']);

        // Issue #2127 — bloc compliance structuré par pays (contrat #1872).
        $this->assertContains($dz['compliance']['level'], ['production', 'pilot', 'placeholder', 'unknown']);
        $this->assertSame($dz['compliance']['level'], $dz['confidence']);
        $this->assertNotEmpty($dz['compliance']['warning']);
        $this->assertNotEmpty($dz['compliance']['warning_localized']);
        $this->assertSame('docs/payroll/DZ_COMPLIANCE.md', $dz['compliance']['source']);

        // #1951 → #5255 : US était « référencé sans règles de paie »
        // (available = false, level = unknown). Depuis le pack EN, les règles
        // US sont résolubles (pilot) : available = true, level = pilot.
        /** @var array{country: string, currency: string, timezone: string, language: string, available: bool, confidence: string, compliance: array{level: string, warning: string, warning_localized: string, source: string, verified_at: string|null}}|null $us */
        $us = $countries->get('US');
        $this->assertNotNull($us);
        $this->assertTrue($us['available']);
        $this->assertSame('pilot', $us['compliance']['level']);
        $this->assertSame('docs/payroll/US_COMPLIANCE.md', $us['compliance']['source']);
        $this->assertNotEmpty($us['compliance']['warning_localized']);
    }

    // ── Provisioning : pays obligatoire et supporté ───────────────────────

    public function test_provisioning_requires_country(): void
    {
        $service = app(CompanyProvisioningService::class);

        try {
            $service->provisionSharedCompany(['name' => 'Sans pays'], $this->superAdmin());
            $this->fail('Provisioning sans pays devrait lever ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('country', $e->errors());
        }
    }

    public function test_provisioning_rejects_unknown_country(): void
    {
        $service = app(CompanyProvisioningService::class);

        try {
            $service->provisionSharedCompany(['name' => 'Pays bidon', 'country' => 'ZZ'], $this->superAdmin());
            $this->fail('Provisioning avec pays inconnu devrait lever ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('country', $e->errors());
        }
    }

    public function test_provisioning_accepts_supported_country(): void
    {
        $this->assertNotNull(CountryDefaults::find('CI'));
        $this->assertTrue(CountryDefaults::isSupported('ci')); // insensible à la casse
        $this->assertFalse(CountryDefaults::isSupported('ZZ'));
        $this->assertNull(CountryDefaults::find('ZZ'));
    }

    // ── Signup self-service : pays requis ─────────────────────────────────

    public function test_self_service_signup_requires_supported_country(): void
    {
        Mail::fake();

        $base = [
            'email' => 'prospect-'.uniqid().'@example.com',
            'company' => 'Prospect SARL',
            'first_name' => 'Ali',
            'last_name' => 'Ben',
            'requestedWorkflow' => 'self_service',
        ];

        // Sans pays → 422.
        $this->postJson('/api/v1/trial/signup', $base)->assertUnprocessable();

        // Pays inconnu → 422.
        $this->postJson('/api/v1/trial/signup', $base + ['country' => 'ZZ'])->assertUnprocessable();

        // Pays supporté → 200 (le flux existant envoie le code de vérification).
        $this->postJson('/api/v1/trial/signup', $base + ['country' => 'DZ'])
            ->assertOk();
    }

    // ── Gardes RH/paie : pays du tenant verrouillé ─────────────────────────

    public function test_payroll_run_store_is_locked_to_tenant_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        // Un run pour un autre pays que le tenant → 422.
        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'CI',
        ])->assertUnprocessable()->assertJsonValidationErrors('country_code');

        // Le pays du tenant (DZ) passe.
        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
        ])->assertCreated();
    }

    public function test_payroll_run_defaults_to_tenant_country_when_omitted(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ])->assertCreated();
    }

    public function test_salary_structure_store_is_locked_to_tenant_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $this->postJson('/api/v1/salary-structures', [
            'name' => 'Grille CI interdite',
            'base_salary' => 100000,
            'currency' => 'XOF',
            'country_code' => 'CI',
        ])->assertStatus(422);

        $this->postJson('/api/v1/salary-structures', [
            'name' => 'Grille DZ',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
        ])->assertCreated();
    }

    public function test_payroll_operations_refused_when_tenant_country_missing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => '']);
        Sanctum::actingAs($this->makeManager($company));

        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
        ])->assertStatus(422);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Test',
            'last_name' => 'SansPays',
            'email' => 'sanspays-'.uniqid().'@example.com',
        ])->assertStatus(422);
    }

    // ── Simulation : indépendante, sans écriture paie ─────────────────────

    public function test_simulation_creates_no_payroll_data_and_uses_requested_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $beforeSlips = PaySlip::count();
        $beforeRuns = PayrollRun::count();

        // La simulation indépendante accepte un pays hors tenant (CI) mais ne
        // crée AUCUNE donnée de paie (runs, bulletins, structures).
        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 300000,
            'country_code' => 'CI',
        ])->assertOk();

        $this->assertArrayHasKey('income_tax', $response->json('data'));
        $this->assertSame($beforeSlips, PaySlip::count());
        $this->assertSame($beforeRuns, PayrollRun::count());
    }

    private function superAdmin(): SuperAdmin
    {
        // SuperAdmin n'a pas de factory : instance non persistée suffit
        // (provisionSharedCompany n'utilise pas l'acteur pour créer).
        return new SuperAdmin([
            'id' => 1,
            'name' => 'Audit',
            'email' => 'audit@leopardo.test',
        ]);
    }
}
