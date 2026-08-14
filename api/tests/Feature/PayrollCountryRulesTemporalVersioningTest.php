<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PA2-ARCH-004 — Versionnement temporel des regles pays paie.
 *
 * TaxSlab/SocialContribution rows are associated with an effective date
 * (effective_from/effective_to), and AbstractCountryRules::asOf() lets
 * payroll rules resolve which dated row applies as of a given point in
 * time instead of always using now(). This is what makes retroactive
 * recalculation possible for audit purposes: a payroll run for a past
 * period can be recalculated using the rates that were effective *during
 * that period*, even after newer rates have since been configured.
 *
 * `tax_slabs`/`social_contributions` are not part of the shared MVP test
 * fixture (schéma manuel CreatesMvpSchema, F-13 #1569 : plus utilisé par le
 * module Payroll), so this test creates/drops
 * them directly, scoped to this test only.
 */
class PayrollCountryRulesTemporalVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('social_contributions');

        Schema::create('tax_slabs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('country_code', 2);
            $table->string('name', 150);
            $table->decimal('min_amount', 14, 2);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('fixed_deduction', 14, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // ADMIN-PAIE (#1813) : statut du workflow de validation — seules
            // les lignes `active` entrent dans les calculs.
            $table->string('status', 30)->default('active');
            $table->timestampsTz();

            $table->index(['country_code', 'effective_from']);
        });

        Schema::create('social_contributions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('country_code', 2);
            $table->string('name', 150);
            $table->string('code', 50);
            $table->enum('type', ['employee', 'employer']);
            $table->decimal('rate', 8, 4);
            $table->decimal('cap', 14, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // ADMIN-PAIE (#1813) : statut du workflow de validation.
            $table->string('status', 30)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'code', 'effective_from'], 'social_contributions_company_code_effective_unique');
            $table->index(['country_code', 'type', 'effective_from']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('social_contributions');
        Schema::dropIfExists('tax_slabs');

        parent::tearDown();
    }

    /**
     * Two non-overlapping dated SocialContribution rows for the same code
     * (a 9% CNAS employee rate through end of 2025, then 9.5% from 2026
     * onward): asOf() must resolve the rate that was effective on the
     * given date, not just "whatever is effective now".
     */
    public function test_calculate_social_charges_resolves_the_rate_effective_on_the_given_date(): void
    {
        SocialContribution::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (2025)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 9.0,
            'cap' => null,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);

        SocialContribution::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (2026)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 9.5,
            'cap' => null,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $rules = new AlgeriaPayrollRules;

        $pastCharges = $rules->asOf('2025-06-15')->calculateSocialCharges(10000);
        self::assertSame(900.0, $pastCharges['employee'], 'the 2025 rate (9%) must apply for a 2025 date');

        $currentCharges = $rules->asOf('2026-06-15')->calculateSocialCharges(10000);
        self::assertSame(950.0, $currentCharges['employee'], 'the 2026 rate (9.5%) must apply for a 2026 date');
    }

    /**
     * Same principle for TaxSlab: a payroll run recalculated for an old
     * period must use the tax slabs that were effective back then, not
     * slabs configured afterward.
     */
    public function test_tax_slabs_resolve_the_slabs_effective_on_the_given_date(): void
    {
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'DZ payroll tax 2025',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 10,
            'fixed_deduction' => 0,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);

        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'DZ payroll tax 2026',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 20,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $rules = new AlgeriaPayrollRules;

        $pastSlabs = $rules->asOf('2025-06-15')->taxSlabs();
        self::assertCount(1, $pastSlabs);
        self::assertSame(10.0, $pastSlabs[0]['rate']);

        $currentSlabs = $rules->asOf('2026-06-15')->taxSlabs();
        self::assertCount(1, $currentSlabs);
        self::assertSame(20.0, $currentSlabs[0]['rate']);
    }

    /**
     * Without asOf(), behaviour must stay exactly as before this ticket:
     * resolve whatever is effective right now.
     */
    public function test_asof_defaults_to_now_when_not_scoped(): void
    {
        SocialContribution::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (current)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 9.0,
            'cap' => null,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ]);

        $rules = new AlgeriaPayrollRules;

        $charges = $rules->calculateSocialCharges(10000);
        self::assertSame(900.0, $charges['employee']);
    }

    /**
     * asOf(null) resets a scoped clone back to the "use now()" default,
     * mirroring forCompany(null)/forProvince(null) elsewhere in this
     * module.
     */
    public function test_asof_null_resets_to_now(): void
    {
        SocialContribution::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (current)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 9.0,
            'cap' => null,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ]);

        $rules = (new AlgeriaPayrollRules)->asOf('2099-01-01')->asOf(null);

        $charges = $rules->calculateSocialCharges(10000);
        self::assertSame(900.0, $charges['employee']);
    }

    /**
     * A company-specific override for one period, with the global row
     * covering a different period, must each resolve independently
     * through the same forCompany()+asOf() combination PayrollCalculator
     * uses (forCompany($companyId)->asOf($run->period_start)).
     */
    public function test_company_override_and_asof_compose_together(): void
    {
        $companyId = '11111111-1111-1111-1111-111111111111';

        SocialContribution::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (global, 2025)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 9.0,
            'cap' => null,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        SocialContribution::create([
            'company_id' => $companyId,
            'country_code' => 'DZ',
            'name' => 'CNAS Salariale (company override, 2025)',
            'code' => 'CNAS_EMP',
            'type' => 'employee',
            'rate' => 7.0,
            'cap' => null,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        $rules = (new AlgeriaPayrollRules)->forCompany($companyId)->asOf('2025-06-15');

        $charges = $rules->calculateSocialCharges(10000);
        self::assertSame(700.0, $charges['employee'], 'the company-specific override must take priority over the global rate');
    }
}
