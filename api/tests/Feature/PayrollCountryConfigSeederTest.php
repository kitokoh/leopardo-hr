<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use Database\Seeders\PayrollCountryConfigSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #1932 — `PayrollCountryConfigSeeder` seedait les `tax_slabs` avec un
 * `effective_from` codé en dur à 2026-01-01 alors que le `name` (« BF payroll
 * tax 2024 ») et `$effectiveFrom` (2024-01-01 pour BF/ML/CI) annoncent 2024.
 * Données auto-contradictoires : un recalcul rétroactif d'un run 2024/2025
 * (`asOf()`, PA2-ARCH-004) ignorait les lignes DB non encore effectives et
 * retombait silencieusement sur les défauts codés — identiques aujourd'hui,
 * divergents dès qu'un expert corrige les taux en base.
 *
 * `tax_slabs`/`social_contributions` ne font pas partie du fixture MVP du
 * schéma de test (voir PayrollCountryRulesTemporalVersioningTest) : ces
 * tables sont créées/détruites ici, scopées à ce test uniquement, avec le
 * schéma des migrations tenant (colonne `status` du workflow #1813 incluse).
 */
class PayrollCountryConfigSeederTest extends TestCase
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
            $table->string('status', 20)->default('active')->index();
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
            $table->string('status', 20)->default('active')->index();
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
     * BF — les 4 cotisations CNSS 2024 sont seedées avec
     * `effective_from = 2024-01-01` et le statut `active` exigé par le
     * moteur (seules les lignes actives sont utilisées, issue #1813).
     */
    public function test_bf_social_contributions_seeded_with_2024_effective_from(): void
    {
        (new PayrollCountryConfigSeeder)->run();

        $contributions = SocialContribution::where('country_code', 'BF')->get();

        self::assertCount(4, $contributions, 'BF doit avoir 4 cotisations CNSS seedées');
        self::assertSame(
            ['CNSS_BF_AT_PAT', 'CNSS_BF_FAM_PAT', 'CNSS_BF_RET_EMP', 'CNSS_BF_RET_PAT'],
            $contributions->pluck('code')->sort()->values()->all()
        );

        foreach ($contributions as $contribution) {
            self::assertSame(
                '2024-01-01',
                $contribution->effective_from->toDateString(),
                "effective_from de {$contribution->code} doit être 2024-01-01 (pas 2026-01-01 en dur)"
            );
            // getAttribute() : le PHPDoc du modèle type `effective_to` comme
            // Carbon non-nullable, mais le seeder pose volontairement null
            // (validité ouverte) — lecture brute pour tester la valeur réelle.
            self::assertNull($contribution->getAttribute('effective_to'));
            self::assertSame(TaxSlab::STATUS_ACTIVE, $contribution->status);
        }
    }

    /**
     * BF — chaque tranche du barème IUTS 2024 est seedée avec
     * `effective_from = 2024-01-01`, cohérente avec le `name`
     * (« BF payroll tax 2024 »). Le compte est aligné sur la source de
     * vérité des règles : l'issue #1932 mentionnait 5 tranches, mais #1915
     * (merge sur main) a rétabli la tranche 27,5 % > 6 M FCFA/an → 6.
     */
    public function test_bf_tax_slabs_seeded_with_2024_effective_from(): void
    {
        (new PayrollCountryConfigSeeder)->run();

        $rules = new CedeaoPayrollRules('BF');
        $slabs = TaxSlab::where('country_code', 'BF')->orderBy('min_amount')->get();

        self::assertCount(count($rules->taxSlabs()), $slabs, 'BF doit avoir une ligne par tranche du barème IUTS');
        self::assertSame(
            array_map(static fn (array $slab): int => (int) $slab['min'], $rules->taxSlabs()),
            $slabs->pluck('min_amount')->map(static fn ($min): int => (int) $min)->values()->all()
        );

        foreach ($slabs as $slab) {
            self::assertStringContainsString('2024', $slab->name, "name du slab doit annoncer 2024 (actuel : {$slab->name})");
            self::assertSame(
                '2024-01-01',
                $slab->effective_from->toDateString(),
                "effective_from de {$slab->name} doit être 2024-01-01 (pas 2026-01-01 en dur)"
            );
            // Idem : null attendu (barème à validité ouverte).
            self::assertNull($slab->getAttribute('effective_to'));
            self::assertSame(TaxSlab::STATUS_ACTIVE, $slab->status);
        }
    }

    /**
     * ML — même contrat que BF : barème ITS 2024 (6 tranches) + 4
     * cotisations INPS, toutes avec `effective_from = 2024-01-01`.
     */
    public function test_ml_seeded_with_2024_effective_from(): void
    {
        (new PayrollCountryConfigSeeder)->run();

        $rules = new CedeaoPayrollRules('ML');

        self::assertCount(
            count($rules->taxSlabs()),
            TaxSlab::where('country_code', 'ML')->get(),
            'ML doit avoir une ligne par tranche du barème ITS'
        );
        self::assertCount(
            4,
            SocialContribution::where('country_code', 'ML')->get(),
            'ML doit avoir 4 cotisations INPS seedées'
        );

        foreach (TaxSlab::where('country_code', 'ML')->get() as $slab) {
            self::assertSame('2024-01-01', $slab->effective_from->toDateString());
        }

        foreach (SocialContribution::where('country_code', 'ML')->get() as $contribution) {
            self::assertSame('2024-01-01', $contribution->effective_from->toDateString());
        }
    }

    /**
     * Re-run idempotent : un second appel du seeder ne duplique aucune ligne
     * et ne change pas les `effective_from` (updateOrCreate sur la même
     * clé métier).
     */
    public function test_seeder_is_idempotent_for_bf(): void
    {
        (new PayrollCountryConfigSeeder)->run();
        $slabCount = TaxSlab::where('country_code', 'BF')->count();
        $contributionCount = SocialContribution::where('country_code', 'BF')->count();

        (new PayrollCountryConfigSeeder)->run();

        self::assertSame($slabCount, TaxSlab::where('country_code', 'BF')->count());
        self::assertSame($contributionCount, SocialContribution::where('country_code', 'BF')->count());
        self::assertSame(
            0,
            TaxSlab::where('country_code', 'BF')->where('effective_from', '!=', '2024-01-01')->count()
        );
    }

    /**
     * Le correctif est scopé : les pays hors BF/ML/CI/CM gardent le
     * comportement historique (effective_from 2026-01-01).
     */
    public function test_other_countries_keep_2026_effective_from(): void
    {
        (new PayrollCountryConfigSeeder)->run();

        $dzSlabs = TaxSlab::where('country_code', 'DZ')->get();

        self::assertGreaterThan(0, $dzSlabs->count(), 'DZ doit être seedé');
        foreach ($dzSlabs as $slab) {
            self::assertSame('2026-01-01', $slab->effective_from->toDateString());
        }
    }
}
