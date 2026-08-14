<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Database\Seeders\PayrollCountryConfigSeeder;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1932 — le seeder BF/ML codait effective_from des tax_slabs en dur
 * à 2026-01-01 alors que le name annonce « BF payroll tax 2024 ».
 * Vérifie que les slabs/cotisations BF portent la bonne date d'effet et
 * que le re-run est idempotent.
 */
class PayrollCountryConfigSeederTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_bf_seeder_uses_2024_effective_from_for_slabs_and_contributions(): void
    {
        (new PayrollCountryConfigSeeder)->run();

        /** @var TaxSlab[] $slabs */
        $slabs = TaxSlab::query()->where('country_code', 'BF')->orderBy('min_amount')->get();
        $this->assertCount(5, $slabs, 'BF doit avoir 5 tranches IUTS');
        foreach ($slabs as $slab) {
            $this->assertSame('2024-01-01', $slab->effective_from->toDateString());
            $this->assertStringContainsString('2024', (string) $slab->name);
        }

        /** @var SocialContribution[] $contributions */
        $contributions = SocialContribution::query()->where('country_code', 'BF')->get();
        $this->assertCount(4, $contributions, 'BF doit avoir 4 cotisations CNSS');
        foreach ($contributions as $contribution) {
            $this->assertSame('2024-01-01', $contribution->effective_from->toDateString());
        }
    }

    public function test_bf_seeder_rerun_is_idempotent(): void
    {
        (new PayrollCountryConfigSeeder)->run();
        (new PayrollCountryConfigSeeder)->run();

        $this->assertSame(5, TaxSlab::query()->where('country_code', 'BF')->count());
        $this->assertSame(4, SocialContribution::query()->where('country_code', 'BF')->count());
    }
}
