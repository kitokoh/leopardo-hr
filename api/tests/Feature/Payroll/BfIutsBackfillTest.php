<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use Database\Seeders\PayrollCountryConfigSeeder;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2003 — tranche IUTS BF 27,5 % (> 6 000 000 FCFA/an) absente des
 * `tax_slabs` SEEDÉS en base.
 *
 * Le fix #1972 a rétabli la 6e tranche dans le code (`defaultTaxSlabs()`),
 * mais les tenants BF seedés depuis #1829 gardent 5 tranches en base (le
 * seeder re-seedait depuis `taxSlabs()` = base → no-op silencieux). Cette
 * suite vérifie le backfill de la migration
 * `2026_08_14_000012_backfill_bf_iuts_27_5_slab` et le seeder corrigé
 * (`legalReferenceTaxSlabs()`).
 */
class BfIutsBackfillTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Reproduit l'état legacy d'un tenant BF seedé depuis #1829 : 5 tranches,
     * la 5e (4 500 001) fusionnée avec la tranche finale (`max_amount = NULL`).
     */
    private function seedLegacyBfSlabs(): void
    {
        // La migration 000021 (barème BF complet en base) pré-seede les 6
        // tranches nationales sur une base fraîche — on repart d'une table
        // vide pour reproduire fidèlement l'état legacy d'un tenant #1829.
        TaxSlab::where('country_code', 'BF')
            ->whereNull('company_id')
            ->delete();

        $rows = [
            [0, 600000, 0.0],
            [600001, 1500000, 12.1],
            [1500001, 3000000, 13.9],
            [3000001, 4500000, 18.7],
            [4500001, null, 23.6], // fusionnée — l'ancien barème
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

    private function runMigration(): void
    {
        // #1962 : la migration a été renumérotée 000012 (collision de basename
        // avec le trigger append-only 000011, résolu par la garde anti-collision).
        $migration = require database_path('migrations/tenant/2026_08_14_000012_backfill_bf_iuts_27_5_slab.php');
        $migration->up();
    }

    public function test_migration_backfills_27_5_slab_for_legacy_bf_tenant(): void
    {
        $this->seedLegacyBfSlabs();

        $this->runMigration();

        $slabs = TaxSlab::where('country_code', 'BF')
            ->whereNull('company_id')
            ->orderBy('min_amount')
            ->get();

        // 6 tranches désormais (borne 6 M + tranche 27,5 %).
        $this->assertCount(6, $slabs);

        /** @var TaxSlab $slab4 */
        $slab4 = $slabs[4];
        /** @var TaxSlab $slab5 */
        $slab5 = $slabs[5];

        $this->assertSame(6_000_000.0, (float) $slab4->max_amount, 'tranche 4 500 001 bornée à 6 000 000');
        $this->assertSame(6_000_001.0, (float) $slab5->min_amount);
        $this->assertNull($slab5->max_amount);
        $this->assertSame(27.5, (float) $slab5->rate);
        $this->assertSame(TaxSlab::STATUS_ACTIVE, $slab5->status);
        $this->assertSame('2024-01-01', $slab5->effective_from->toDateString());
    }

    public function test_migration_is_idempotent(): void
    {
        $this->seedLegacyBfSlabs();

        $this->runMigration();
        $countAfterFirst = TaxSlab::where('country_code', 'BF')->count();

        $this->runMigration();
        $countAfterSecond = TaxSlab::where('country_code', 'BF')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(
            1,
            TaxSlab::where('country_code', 'BF')->where('min_amount', 6_000_001)->count(),
        );
    }

    public function test_golden_iuts_after_backfill_matches_legal_bracket(): void
    {
        $this->seedLegacyBfSlabs();
        $this->runMigration();

        // Les règles résolvent depuis la BASE (comme le moteur en production) :
        // le backfill doit rendre la 6e tranche effective.
        $rules = new CedeaoPayrollRules('BF');

        // Cas frontal #1915 — 500 000/mois → annuel 6 000 000, borne 23,6/27,5 :
        // IUTS mensuel 79 325,00.
        $this->assertSame(79325.0, $rules->calculateIncomeTax(500000.0));
        // 525 000/mois → annuel 6 300 000 > 6 M : 86 200,00 (tranche 27,5 %).
        $this->assertSame(86200.0, $rules->calculateIncomeTax(525000.0));
    }

    public function test_seeder_now_seeds_legal_reference_slabs(): void
    {
        // Le seeder corrigé seede depuis `legalReferenceTaxSlabs()` (le code,
        // source légale) et non depuis `taxSlabs()` (base) — un re-seed après
        // le backfill restaure le barème complet même si la base divergeait.
        $this->seedLegacyBfSlabs();

        (new PayrollCountryConfigSeeder)->run();

        $slabs = TaxSlab::where('country_code', 'BF')
            ->whereNull('company_id')
            ->orderBy('min_amount')
            ->get();

        $this->assertCount(6, $slabs, 'le re-seed doit rétablir les 6 tranches IUTS');
        $last = $slabs->last();
        $this->assertNotNull($last, 'au moins une tranche IUTS re-seedée');
        $this->assertSame(27.5, (float) $last->rate);
    }

    public function test_non_bf_countries_unaffected(): void
    {
        // Garde-fou : le backfill ne touche que le Burkina Faso.
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'CI',
            'name' => 'CI payroll tax 2024',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 23.6,
            'fixed_deduction' => 0,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);

        $this->runMigration();

        $ci = TaxSlab::where('country_code', 'CI')->get();
        $this->assertCount(1, $ci, 'CI ne doit pas être modifié');
        $first = $ci->first();
        $this->assertNotNull($first, 'la tranche CI doit rester présente');
        $this->assertNull($first->max_amount);
    }
}
