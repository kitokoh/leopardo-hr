<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #2003 — backfill des tenants BF seedés avant #1972 :
 *
 * `AbstractCountryRules::taxSlabs()` résout la base AVANT le code et
 * `PayrollCountryConfigSeeder` re-seedait depuis `taxSlabs()` → no-op
 * silencieux : les tenants BF gardaient 5 tranches IUTS alors que
 * `CedeaoPayrollRules::defaultTaxSlabs()` en expose 6 depuis #1972
 * (> 6 000 000 FCFA/an @ 27,5 %). Les bulletins BF > ~500 000 FCFA/mois
 * restaient sous-imposés (marginal 23,6 % au lieu de 27,5 %).
 *
 * Ce test simule un tenant BF « legacy » (5 tranches, tranche 4,5 M ouverte
 * à null) puis rejoue la migration `2026_08_14_000009_...` et vérifie :
 *   1. 6 tranches en base, borne 6 000 000, taux 27,5 % ;
 *   2. idempotence (re-run sans doublon) ;
 *   3. golden IUTS : 500 000/mois → 79 325,00 ; 525 000 → 86 200,00.
 */
class BackfillBfIuts27p5MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tax_slabs');

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
            $table->string('status', 20)->default('active')->index();
            $table->timestampsTz();

            $table->index(['country_code', 'effective_from']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tax_slabs');

        parent::tearDown();
    }

    /**
     * État legacy #1829 : 5 tranches, la 4,5 M est ouverte (max null),
     * PAS de tranche 27,5 %.
     */
    private function seedLegacyBf5Slabs(): void
    {
        $slabs = [
            ['min' => 0, 'max' => 600000, 'rate' => 0],
            ['min' => 600001, 'max' => 1500000, 'rate' => 12.1],
            ['min' => 1500001, 'max' => 3000000, 'rate' => 13.9],
            ['min' => 3000001, 'max' => 4500000, 'rate' => 18.7],
            ['min' => 4500001, 'max' => null, 'rate' => 23.6],
        ];

        foreach ($slabs as $i => $slab) {
            DB::table('tax_slabs')->insert([
                'company_id' => null,
                'country_code' => 'BF',
                'name' => "BF payroll tax 2024 tranche {$i}",
                'min_amount' => $slab['min'],
                'max_amount' => $slab['max'],
                'rate' => $slab['rate'],
                'fixed_deduction' => 0,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'status' => TaxSlab::STATUS_ACTIVE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function runMigration(): void
    {
        $migration = require base_path('api/database/migrations/tenant/2026_08_14_000009_backfill_bf_iuts_27p5_tranche.php');
        $migration->up();
    }

    public function test_legacy_bf_tenant_gets_sixth_tranche_and_closed_bound(): void
    {
        $this->seedLegacyBf5Slabs();

        self::assertSame(5, DB::table('tax_slabs')->where('country_code', 'BF')->count());

        $this->runMigration();

        $slabs = DB::table('tax_slabs')
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->where('status', 'active')
            ->orderBy('min_amount')
            ->get();

        self::assertCount(6, $slabs, 'BF doit avoir 6 tranches après backfill');

        $boundary = $slabs->firstWhere('min_amount', 4500001);
        self::assertNotNull($boundary);
        self::assertEquals(6000000, (float) $boundary->max_amount, 'tranche 4,5 M plafonnée à 6 M');

        $sixth = $slabs->firstWhere('min_amount', 6000001);
        self::assertNotNull($sixth, 'tranche 6 000 001 insérée');
        self::assertEquals(27.5, (float) $sixth->rate, 'taux 27,5 % sur la 6e tranche');
        self::assertNull($sixth->max_amount, '6e tranche ouverte');
        self::assertSame('active', $sixth->status);
        self::assertSame('2024-01-01', substr((string) $sixth->effective_from, 0, 10));
    }

    public function test_migration_is_idempotent(): void
    {
        $this->seedLegacyBf5Slabs();

        $this->runMigration();
        $this->runMigration(); // re-run

        self::assertSame(
            6,
            DB::table('tax_slabs')->where('country_code', 'BF')->count(),
            're-run ne doit pas dupliquer les tranches'
        );
    }

    public function test_golden_iuts_after_backfill(): void
    {
        $this->seedLegacyBf5Slabs();
        $this->runMigration();

        // Barème résolu DEPUIS LA BASE (comme en prod) : les 6 tranches doivent
        // donner les mêmes montants que les golden du code (#1915).
        $rules = new \App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules('BF');

        // Assiette mensuelle 500 000 → annuel 6 000 000 (limite 23,6/27,5 %).
        self::assertSame(79325.0, $rules->calculateIncomeTax(500000.0));
        // Assiette mensuelle 525 000 → annuel 6 300 000 (> 6 M → 27,5 %).
        self::assertSame(86200.0, $rules->calculateIncomeTax(525000.0));
    }
}
