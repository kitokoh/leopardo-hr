<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Database\Migrations\GuardedMigration;
use App\Database\Migrations\MigrationOrderInspector;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * #9923 — le garde d'existence d'une migration doit etre BRUYANT, et le
 * desordre d'ordre d'execution qui le declenche doit etre detecte.
 *
 * Le defaut #9896 est ne d'un `return` muet : la migration RLS a tourne avant
 * la creation de sa table, son garde l'a sautee, et Laravel l'a marquee
 * « Ran ». Etat final : `migrate:status` vert, protection absente.
 *
 * Ces tests verrouillent les deux moities de la reponse :
 *   1. `GuardedMigration` trace le saut (warning pour table absente, info pour
 *      idempotence — confondre les deux noierait le signal) ;
 *   2. `MigrationOrderInspector` detecte le desordre qui produit ce genre de
 *      saut, y compris le cas reel #9896.
 *
 * Aucun de ces tests ne depend de l'etat de la base : la logique de detection
 * est pure, et elle est testee sur des jeux de lignes explicites.
 */
final class MigrationGuardObservabilityTest extends TestCase
{
    /**
     * Un saut pour table absente doit produire un WARNING, pas un silence.
     */
    public function test_skip_because_table_missing_logs_a_warning(): void
    {
        Log::spy();

        $migration = new class extends GuardedMigration
        {
            public function up(): void
            {
                $this->skipBecauseTableMissing('table_qui_nexiste_pas', 'colonne non ajoutee');
            }
        };

        $migration->up();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(static function (string $message, array $context): bool {
                return str_contains($message, '#9923')
                    && $context['table'] === 'table_qui_nexiste_pas'
                    && $context['consequence'] === 'colonne non ajoutee';
            });
    }

    /**
     * Un saut pour colonne deja presente est de l'idempotence normale : il est
     * trace en `info`, jamais en `warning`.
     */
    public function test_skip_because_column_exists_logs_info_not_warning(): void
    {
        Log::spy();

        $migration = new class extends GuardedMigration
        {
            public function up(): void
            {
                $this->skipBecauseColumnExists('employees', 'cnps_matricule');
            }
        };

        $migration->up();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => $context['column'] === 'cnps_matricule');

        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Le cas reel #9896 : la migration RLS (horodatage 000200) a tourne en
     * batch 2, la creation de sa table (horodatage 000100) en batch 3.
     *
     * L'inspecteur doit rendre la paire (creation, RLS) — c'est ce couple qui
     * explique le saut silencieux.
     */
    public function test_inspector_detects_the_real_9896_case(): void
    {
        $rows = [
            ['migration' => '2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims', 'batch' => 2],
            ['migration' => '2026_11_24_000100_9874_create_bulk_payment_claims_table', 'batch' => 3],
        ];

        $out = MigrationOrderInspector::outOfOrder($rows);

        $this->assertCount(1, $out);
        $this->assertSame('2026_11_24_000100_9874_create_bulk_payment_claims_table', $out[0]['older']);
        $this->assertSame(3, $out[0]['older_batch']);
        $this->assertSame('2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims', $out[0]['newer']);
        $this->assertSame(2, $out[0]['newer_batch']);
    }

    /**
     * Un ordre sain ne produit aucun desordre : batches croissants avec les
     * horodatages, y compris plusieurs migrations dans un meme batch.
     */
    public function test_inspector_reports_nothing_on_a_healthy_ordering(): void
    {
        $rows = [
            ['migration' => '2026_01_01_000100_a', 'batch' => 1],
            ['migration' => '2026_01_01_000200_b', 'batch' => 1],
            ['migration' => '2026_01_02_000100_c', 'batch' => 2],
            ['migration' => '2026_01_03_000100_d', 'batch' => 3],
        ];

        $this->assertSame([], MigrationOrderInspector::outOfOrder($rows));
    }

    /**
     * Une migration sans prefixe horodatable est ignoree : on ne peut pas la
     * comparer, et l'inventer produirait un faux positif.
     */
    public function test_inspector_ignores_migrations_without_a_timestamp_prefix(): void
    {
        $rows = [
            ['migration' => 'sans_prefixe', 'batch' => 9],
            ['migration' => '2026_01_01_000100_a', 'batch' => 1],
        ];

        $this->assertNull(MigrationOrderInspector::timestampOf('sans_prefixe'));
        $this->assertSame([], MigrationOrderInspector::outOfOrder($rows));
    }

    /**
     * La commande expose un verdict exploitable par un pipeline : 0 = sain,
     * 1 = desordre, 2 = base non migree. Elle ne doit jamais planter.
     */
    public function test_command_reports_a_clear_verdict(): void
    {
        $exit = $this->artisan('migrations:skipped-effects', ['--json' => true])->run();

        $this->assertContains($exit, [0, 1, 2]);
    }
}
