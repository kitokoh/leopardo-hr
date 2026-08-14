<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1872 — exposition du niveau de confiance / avertissement de
 * conformité aux clients web et mobile.
 *
 * - GET /api/v1/supported-countries : chaque pays expose compliance_warning_key,
 *   compliance_warning (localisé), legal_sources et verified_at ;
 * - le contrat de calcul (présentateur) porte un bloc `compliance` structuré ;
 * - les pays sans règles de paie (ex. GB/US) → confidence=unknown, warning null.
 */
class SupportedCountryComplianceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsManager(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI']);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);
    }

    public function test_supported_countries_expose_compliance_block(): void
    {
        $this->actingAsManager();

        /** @var array<int, array<string, mixed>> $registry */
        $registry = $this->getJson('/api/v1/supported-countries')->assertOk()->json('data');

        $ci = collect($registry)->firstWhere('country', 'CI');
        $this->assertNotNull($ci);
        $this->assertSame('pilot', $ci['confidence']);
        $this->assertTrue($ci['available']);
        $this->assertSame('payroll.compliance_warning_pilot', $ci['compliance_warning_key']);
        $this->assertNotEmpty($ci['compliance_warning']);
        $this->assertContains('CGI Côte d\'Ivoire', $ci['legal_sources']);
        $this->assertNull($ci['verified_at']); // #1904 — validation experte non livrée

        // Pays référencé sans règles de paie dédiées → inconnu, pas d'avertissement.
        $us = collect($registry)->firstWhere('country', 'US');
        $this->assertNotNull($us);
        $this->assertSame('unknown', $us['confidence']);
        $this->assertFalse($us['available']);
        $this->assertNull($us['compliance_warning_key']);
        $this->assertNull($us['compliance_warning']);
        $this->assertSame([], $us['legal_sources']);
    }

    public function test_supported_countries_warning_is_localized_fr(): void
    {
        $this->actingAsManager();

        $this->withHeader('Accept-Language', 'fr');
        $this->app->setLocale('fr');

        /** @var array<int, array<string, mixed>> $registry */
        $registry = $this->getJson('/api/v1/supported-countries')->assertOk()->json('data');

        $ci = collect($registry)->firstWhere('country', 'CI');
        $this->assertStringContainsString('Règles pilote', (string) $ci['compliance_warning']);
        $this->assertStringContainsString('Côte d\'Ivoire', (string) $ci['compliance_warning']);
    }

    public function test_calculation_contract_carries_compliance_block(): void
    {
        $contract = (new PayrollCalculationPresenter)->present('CI', 500000.0);

        $this->assertSame('pilot', $contract['compliance']['confidence_level']);
        $this->assertSame('payroll.compliance_warning_pilot', $contract['compliance']['warning_key']);
        $this->assertNotEmpty($contract['compliance']['warning']);
        $this->assertContains('CNSS CI', $contract['compliance']['sources']);
        $this->assertNull($contract['compliance']['verified_at']);

        // Cohérence avec le champ racine historique.
        $this->assertSame($contract['confidence_level'], $contract['compliance']['confidence_level']);
    }
}
