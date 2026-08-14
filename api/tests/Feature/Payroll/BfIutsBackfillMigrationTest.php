<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2003 — la tranche IUTS BF 27,5 % (> 6 000 000 FCFA/an) doit exister
 * dans les tax_slabs SEEDÉS en base, pas seulement dans le code (#1972).
 *
 * Vérifie :
 *  1. le seeder seede depuis la source LÉGALE (`legalDefaultTaxSlabs()`) :
 *     re-seeder un état legacy 5 tranches → 6 tranches, borne 6 000 000 ;
 *  2. la migration de backfill (000012) borne la tranche 4 500 001 et
 *     insère la 27,5 % (idempotent, gardes F-17) ;
 *  3. le golden #1972 est atteint DEPUIS LA BASE : 500 000/mois → 79 325,00
 *     (borne 6 M annuel) et 525 000/mois → 86 200,00 (27,5 % appliqué).
 */
class BfIutsBackfillMigrationTest extends TestCase
{
    use RefreshTenantDatabase;

    /** État legacy seedé depuis #1829 (5 tranches, 23,6 % ouverte). */
    private function seedLegacyBfScale(): void
    {
        $rows = [
            [0, 600000, 0.0],
            [600001, 1500000, 12.1],
            [1500001, 3000000, 13.9],
            [3000001, 4500000, 18.7],
            [4500001, null, 23.6],
        ];

        foreach ($rows as [$min, $max, $rate]) {
            TaxSlab::create([
                'company_id' => null,
                'country_code' => 'BF',
                'name' => 'BF payroll tax 2024',
                'min_amount' => $min,
                'max_amount' => $max,
                'rate' => $rate,
                'fixed_deduction' => 0,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'status' => TaxSlab::STATUS_ACTIVE,
            ]);
        }
    }

    public function test_bf_seeded_slabs_include_27_5_percent_tranche(): void
    {
        // État legacy (5 tranches) → re-seed depuis la source légale :
        // la 6e tranche (27,5 %) doit apparaître, la 4e bornée à 6 000 000.
        $this->seedLegacyBfScale();

        Artisan::call('db:seed', ['--class' => 'PayrollCountryConfigSeeder', '--force' => true]);

        /** @var array<int, array{min_amount: int, max_amount: int|null, rate: float}> $slabs */
        $slabs = TaxSlab::query()
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->orderBy('min_amount')
            ->get(['min_amount', 'max_amount', 'rate'])
            ->map(fn (TaxSlab $s): array => [
                'min_amount' => (int) $s->min_amount,
                'max_amount' => $s->max_amount === null ? null : (int) $s->max_amount,
                'rate' => (float) $s->rate,
            ])
            ->all();

        $this->assertCount(6, $slabs);
        $this->assertSame(6000000, $slabs[4]['max_amount']); // 4 500 001 → 6 000 000
        $this->assertSame(6000001, $slabs[5]['min_amount']);
        $this->assertNull($slabs[5]['max_amount']);
        $this->assertSame(27.5, $slabs[5]['rate']);
    }

    public function test_backfill_migration_is_idempotent_and_repairs_legacy_state(): void
    {
        $this->seedLegacyBfScale();

        $migration = require base_path('database/migrations/tenant/2026_08_14_000012_backfill_bf_iuts_27_5_tranche.php');
        $migration->up();
        $migration->up(); // idempotence

        $slabs = TaxSlab::query()
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->orderBy('min_amount')
            ->get(['min_amount', 'max_amount', 'rate']);

        $this->assertCount(6, $slabs);

        $bound = $slabs->firstWhere('min_amount', 4500001);
        $this->assertNotNull($bound);
        $this->assertSame(6000000, (int) $bound->max_amount);

        $top = $slabs->firstWhere('min_amount', 6000001);
        $this->assertNotNull($top);
        $this->assertNull($top->max_amount);
        $this->assertSame(27.5, (float) $top->rate);
        $this->assertSame(TaxSlab::STATUS_ACTIVE, $top->status);
    }

    public function test_bf_iuts_golden_reached_from_seeded_database(): void
    {
        // Base réparée (backfill) → le calcul BF passe par les lignes DB.
        $this->seedLegacyBfScale();
        $migration = require base_path('database/migrations/tenant/2026_08_14_000012_backfill_bf_iuts_27_5_tranche.php');
        $migration->up();

        // Résolution via le résolveur (comme le moteur) — base avant code.
        $rules = (new CountryRulesResolver)->resolve('BF');

        // Golden #1972 : assiette 500 000/mois → annuel 6 000 000 (borne
        // 23,6 %/27,5 %) → 79 325,00 ; 525 000 → 86 200,00 (27,5 % appliqué).
        $this->assertSame(79325.0, $rules->calculateIncomeTax(500000.0));
        $this->assertSame(86200.0, $rules->calculateIncomeTax(525000.0));

        // Les lignes résolues sont bien celles de la base (6 tranches).
        $this->assertCount(6, $rules->taxSlabs());
    }
}
