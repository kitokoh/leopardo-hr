<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\PayrollBalanceUnavailableException;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\PayrollCycleService;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-3 (#1663) — Durcissement paie : NOT NULL effectivity, erreurs visibles,
 * validations `after:effective_from`.
 *
 * Couvre :
 *   1. `effective_to` doit être strictement postérieur à `effective_from`
 *      (store ET update partiel) pour social_contributions et tax_slabs ;
 *   2. les updates partiels sans `effective_from` restent acceptés
 *      (pas de régression de la validation conditionnelle) ;
 *   3. `safeEmployeeBalance` ne renvoie plus de valeurs vides : l'échec de
 *      calcul du solde → HTTP 500 explicite + erreur structurée.
 */
class PayrollHardeningTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;

        Sanctum::actingAs($this->manager);
    }

    // ── effective_to après effective_from : social_contributions ───────────

    public function test_social_contribution_store_rejects_effective_to_before_effective_from(): void
    {
        $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'CNAS',
            'code' => 'CNAS_TEST_AFTER',
            'type' => 'employee',
            'rate' => 9.0,
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-07-01',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('effective_to');
    }

    public function test_social_contribution_update_rejects_effective_to_before_effective_from(): void
    {
        $contribution = SocialContribution::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'CNAS',
            'code' => 'CNAS_TEST_AFTER_UPD',
            'type' => 'employee',
            'rate' => 9.0,
            'effective_from' => '2026-08-01',
        ]);

        $this->putJson("/api/v1/social-contributions/{$contribution->id}", [
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-07-31',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('effective_to');
    }

    public function test_social_contribution_update_partial_without_effective_from_still_works(): void
    {
        $contribution = SocialContribution::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'CNAS',
            'code' => 'CNAS_TEST_AFTER_PARTIAL',
            'type' => 'employee',
            'rate' => 9.0,
            'effective_from' => '2026-08-01',
        ]);

        $this->putJson("/api/v1/social-contributions/{$contribution->id}", [
            'rate' => 9.5,
        ])->assertOk()
            ->assertJsonPath('data.code', 'CNAS_TEST_AFTER_PARTIAL');
    }

    // ── effective_to après effective_from : tax_slabs ──────────────────────

    public function test_tax_slab_update_rejects_effective_to_before_effective_from(): void
    {
        $slab = TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 20000,
            'rate' => 0.0,
            'effective_from' => '2026-08-01',
        ]);

        $this->putJson("/api/v1/tax-slabs/{$slab->id}", [
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-07-31',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('effective_to');
    }

    public function test_tax_slab_update_partial_without_effective_from_still_works(): void
    {
        $slab = TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 20000,
            'rate' => 0.0,
            'effective_from' => '2026-08-01',
        ]);

        $this->putJson("/api/v1/tax-slabs/{$slab->id}", [
            'max_amount' => 21000,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Tranche 1');
    }

    // ── Erreur visible au lieu de valeurs vides (safeEmployeeBalance) ──────

    public function test_mobile_summary_returns_500_explicit_error_when_balance_fails(): void
    {
        $this->app->instance(PayrollCycleService::class, new class extends PayrollCycleService
        {
            public function getMobileSummary(Employee $actor, int $limit = 50): array
            {
                throw new PayrollBalanceUnavailableException('solde indisponible (test)');
            }
        });

        $this->getJson('/api/v1/payroll/mobile-summary')
            ->assertStatus(500)
            ->assertJsonPath('error', 'PAYROLL_BALANCE_UNAVAILABLE');
    }

    public function test_mobile_summary_does_not_return_zeroed_fallback_payload(): void
    {
        $this->app->instance(PayrollCycleService::class, new class extends PayrollCycleService
        {
            public function getMobileSummary(Employee $actor, int $limit = 50): array
            {
                throw new PayrollBalanceUnavailableException('solde indisponible (test)');
            }
        });

        $response = $this->getJson('/api/v1/payroll/mobile-summary');

        $response->assertStatus(500);
        $content = $response->json();
        $this->assertArrayNotHasKey('data', $content);
        $this->assertNotContains('partial_balance_fallback', $content);
    }

    /**
     * Le vrai `safeEmployeeBalance()` (non court-circuité) doit propager
     * l'échec de `getEmployeeBalance()` en `PayrollBalanceUnavailableException`
     * — jamais renvoyer le payload de secours à zéros (S-3, #1663).
     */
    public function test_real_safe_employee_balance_propagates_balance_failure(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);

        /** @var PayrollCycleService&MockInterface $service */
        $service = Mockery::mock(PayrollCycleService::class)->makePartial();
        /** @var Expectation $expectation */
        $expectation = $service->shouldReceive('getEmployeeBalance');
        $expectation->once()->andThrow(new \RuntimeException('paie indisponible (test)'));

        $this->expectException(PayrollBalanceUnavailableException::class);
        $this->expectExceptionMessage('paie indisponible (test)');

        $service->getMobileSummary($manager);
    }
}
