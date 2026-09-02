<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6727 — le détail par tranche (`income_tax_by_slab`) du simulateur
 * doit converger vers l'impôt réel (`income_tax`) pour les pays à abattement.
 *
 * Repro prod 2026-09-01 (gross 300 000) :
 *   SN : income_tax 39 460 vs Σ tranches 61 960 → Δ +22 500 (= abattement
 *       30 % plafonné 75 000 non appliqué avant le barème)
 *   DZ : income_tax 75 190 vs Σ tranches 76 690 → Δ +1 500 (= réduction IRG
 *       post-barème non appliquée)
 *   MA : income_tax 108 338,12 vs Σ tranches 109 288,13 → Δ +950 (= abattement
 *       frais pro marocain non appliqué)
 */
class PayrollSlabBreakdownAbatementTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    /**
     * expectedTax = null quand la valeur prod du repro n'est pas
     * reproductible en env de test (slabs du tenant différents des slabs
     * nationaux) — l'invariant Σ tranches == income_tax reste vérifié.
     *
     * @return array<string, array{country: string, gross: float, expectedTax: float|null}>
     */
    public static function abatementCountriesProvider(): array
    {
        return [
            // Repro #6727 (prod, gross 300 000) : SN income_tax 39 460 vs Σ 61 960 (Δ +22 500).
            'SN Sénégal' => ['country' => 'SN', 'gross' => 300000.0, 'expectedTax' => 39460.0],
            // Repro #6727 (prod, gross 300 000) : DZ income_tax 75 190 vs Σ 76 690 (Δ +1 500).
            'DZ Algérie' => ['country' => 'DZ', 'gross' => 300000.0, 'expectedTax' => 75190.0],
            // Repro #6727 (prod, gross 300 000) : MA income_tax 108 338,12 vs Σ 109 288,13 (Δ +950).
            // En env de test (slabs nationaux sans override tenant) le moteur
            // produit 105 025,23 — seule l'invariant Σ == income_tax est asserté.
            'MA Maroc' => ['country' => 'MA', 'gross' => 300000.0, 'expectedTax' => null],
        ];
    }

    #[DataProvider('abatementCountriesProvider')]
    public function test_slab_breakdown_sums_to_income_tax_for_abatement_countries(string $country, float $gross, ?float $expectedTax): void
    {
        Sanctum::actingAs($this->manager);

        $data = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => $country,
            'gross_salary' => $gross,
        ])->assertOk()->json('data');

        $incomeTax = (float) $data['income_tax'];
        $slabs = $data['income_tax_by_slab'];

        $this->assertNotEmpty($slabs, "tranches manquantes pour {$country}");
        $this->assertNotEmpty($data['income_tax'], "impôt manquant pour {$country}");

        // Contrat #6727 : Σ tax des tranches == income_tax (tolérance arrondi).
        $slabTotal = array_sum(array_map(fn (array $slab): float => (float) $slab['tax'], $slabs));
        $this->assertEqualsWithDelta(
            $incomeTax,
            $slabTotal,
            0.02,
            "Σ income_tax_by_slab.tax ({$slabTotal}) != income_tax ({$incomeTax}) pour {$country}",
        );

        // Valeurs golden du repro #6727 (gross 300 000), quand reproductibles.
        if ($expectedTax !== null) {
            $this->assertEqualsWithDelta($expectedTax, $incomeTax, 0.02, "income_tax inattendu pour {$country}");
        }
    }

    public function test_slab_breakdown_unchanged_for_country_without_abatement(): void
    {
        // CI (Côte d'Ivoire) : pas d'abattement — le candidat historique
        // (base imposable mensuelle) reste retenu et converge toujours.
        Sanctum::actingAs($this->manager);

        $data = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'CI',
            'gross_salary' => 300000,
        ])->assertOk()->json('data');

        $incomeTax = (float) $data['income_tax'];
        $slabTotal = array_sum(array_map(fn (array $slab): float => (float) $slab['tax'], $data['income_tax_by_slab']));

        $this->assertEqualsWithDelta($incomeTax, $slabTotal, 0.02, 'CI sans abattement : Σ tranches doit rester == income_tax');
    }
}
