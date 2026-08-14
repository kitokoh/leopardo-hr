<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2220 [PAYROLL][P1] — parité simulateur ↔ bulletin.
 *
 * Invariant #1869 : « la simulation et le bulletin produisent les mêmes
 * résultats ». Vérifie :
 *  - SN : TRIMF déduite par le simulateur (net simulate = net bulletin) ;
 *  - income_tax_by_slab annualisé pour MA/FR/BF/ML/CG/GA (converge vers
 *    income_tax) ;
 *  - cotisation-simulation SN T2 / FR CSG : assiettes réelles (tranche
 *    432 001–2 160 000, 98,25 % du brut).
 */
class SimulateParityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    public function test_sn_simulate_includes_trimf_like_bulletin(): void
    {
        // Issue #2220 — brut 100 000 XOF, SN : TRIMF 5 400 > IR 2 380 →
        // net bulletin = 89 000 (max(IR, TRIMF) déduit).
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'SN',
            'gross_salary' => 100000,
        ])->assertOk();

        $data = $response->json('data');

        // La TRIMF (max(IR, TRIMF), combineMinimumFiscalTax SN) est déduite :
        // net = 100 000 − cotisations − max(IR, TRIMF).
        $this->assertSame('SN', $data['country_code']);
        $this->assertGreaterThan(0.0, (float) $data['income_tax']);
        $this->assertSame(
            round(100000 - (float) $data['social_employee'] - (float) $data['income_tax'] - (float) $data['bracket_tax'], 2),
            (float) $data['net']
        );
        // Parité : même pipeline que le bulletin (computeNetBreakdown) —
        // le net ne peut pas être 92 020 (TRIMF omise).
        $this->assertLessThan(92020.0, (float) $data['net']);
    }

    public function test_income_tax_by_slab_annualized_converges_for_bf(): void
    {
        // BF : barème ANNUEL (IUTS 6 tranches) — base annualisée puis /12 :
        // brut 300 000, CNSS 5,5 % = 16 500 → assiette 283 500 → annuel
        // 3 402 000. Tranches : 0–600k : 0 · 600k–1,5M : 108 900 ·
        // 1,5M–3M : 208 500 · 3M–3,402M : 75 174 → total 392 574 / 12
        // = 32 714,50 = income_tax.
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'BF',
            'gross_salary' => 300000,
        ])->assertOk();

        $data = $response->json('data');

        $slabSum = array_sum(array_map(fn (array $s) => (float) $s['tax'], $data['income_tax_by_slab']));
        $this->assertEqualsWithDelta((float) $data['income_tax'], $slabSum, 0.01);
        // Valeurs attendues calculées à la main (BF_COMPLIANCE.md §1).
        $this->assertEqualsWithDelta(32714.5, (float) $data['income_tax'], 0.01);
    }

    public function test_sn_cotisation_t2_uses_real_bracket(): void
    {
        // SN brut 500 000 : IPRES T2 cadres = tranche 432 001–2 160 000 →
        // assiette 68 000 × 2,4 % = 1 632 (pas brut × 2,4 % = 12 000).
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'SN',
            'gross_salary' => 500000,
        ])->assertOk();

        $data = $response->json('data');

        $t2 = collect($data['employee_contributions'])
            ->firstWhere('code', 'IPRES_SN_EMP_T2');
        $this->assertNotNull($t2);
        $this->assertEqualsWithDelta(1632.0, (float) $t2['amount'], 0.01);
    }

    public function test_fr_csg_uses_9825_base(): void
    {
        // FR brut 3 000 EUR : CSG 9,2 % sur 98,25 % du brut =
        // 2 947,50 × 9,2 % = 271,17 (pas 3 000 × 9,2 % = 276).
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'FR',
            'gross_salary' => 3000,
        ])->assertOk();

        $data = $response->json('data');

        $csg = collect($data['employee_contributions'])->firstWhere('code', 'CSG_FR');
        $this->assertNotNull($csg);
        $this->assertEqualsWithDelta(271.17, (float) $csg['amount'], 0.01);
    }
}
