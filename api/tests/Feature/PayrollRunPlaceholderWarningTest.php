<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5623 — barèmes de paie 'placeholder' (BF/ML/TG…) non signalés :
 * les bulletins pouvaient être générés avec des chiffres incorrects sans
 * aucun avertissement. Contrat :
 *   1. POST /payroll-runs retourne un warning structuré si le pays est
 *      placeholder (aucun pour pilot/production) ;
 *   2. POST /payroll-runs/{run}/validate exige `confirm_placeholder=true`
 *      quand le pays est placeholder (422 sinon).
 */
class PayrollRunPlaceholderWarningTest extends TestCase
{
    use RefreshTenantDatabase;

    private function bindFakeCalculator(string $confidenceLevel): void
    {
        /** @var PayrollCalculator&MockInterface $calculator */
        $calculator = Mockery::mock(PayrollCalculator::class);
        $calculator->shouldReceive('getRules')->andReturnUsing(
            function () use ($confidenceLevel): CountryRulesInterface {
                /** @var CountryRulesInterface&MockInterface $rules */
                $rules = Mockery::mock(CountryRulesInterface::class);
                $rules->shouldReceive('confidenceLevel')->andReturn($confidenceLevel);

                return $rules;
            }
        );
        $this->app->instance(PayrollCalculator::class, $calculator);
    }

    private function bindFakeClosingService(): void
    {
        $closing = Mockery::mock(PayrollClosingService::class);
        $closing->shouldReceive('validateRh')->andReturnUsing(
            fn (PayrollRun $run): PayrollRun => $run
        );
        $this->app->instance(PayrollClosingService::class, $closing);
    }

    public function test_store_returns_warning_for_placeholder_country(): void
    {
        $this->bindFakeCalculator('placeholder');
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'BF']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'country_code' => 'BF',
        ])
            ->assertCreated()
            ->assertJsonPath('warning.code', 'PAYROLL_PLACEHOLDER_COUNTRY')
            ->assertJsonPath('warning.country', 'BF')
            ->assertJsonStructure(['warning' => ['code', 'message', 'country']]);
    }

    public function test_store_has_no_warning_for_pilot_or_production_country(): void
    {
        foreach (['pilot', 'production'] as $level) {
            $this->bindFakeCalculator($level);
            /** @var Company $company */
            $company = Company::factory()->create(['country' => 'CM']);
            /** @var Employee $manager */
            $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

            Sanctum::actingAs($manager);

            $this->postJson('/api/v1/payroll-runs', [
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'country_code' => 'CM',
            ])
                ->assertCreated()
                ->assertJsonMissingPath('warning');
        }
    }

    public function test_validate_placeholder_run_requires_explicit_confirmation(): void
    {
        $this->bindFakeCalculator('placeholder');
        $this->bindFakeClosingService();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'ML',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
        ]);

        Sanctum::actingAs($manager);

        // Sans confirmation → 422 PAYROLL_PLACEHOLDER_CONFIRM_REQUIRED.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertStatus(422)
            ->assertJsonPath('error', 'PAYROLL_PLACEHOLDER_CONFIRM_REQUIRED');

        // Avec confirmation explicite → le flux continue (fake closing OK).
        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate", ['confirm_placeholder' => true])
            ->assertOk();
    }

    public function test_validate_pilot_run_does_not_require_confirmation(): void
    {
        $this->bindFakeCalculator('pilot');
        $this->bindFakeClosingService();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'CM',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'calculated',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertOk();
    }
}
