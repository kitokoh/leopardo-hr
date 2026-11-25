<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Migrations\MigrationOrderInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #9923 — sonde des migrations « passees mais sans effet ».
 *
 * ## Le defaut que cette commande rend visible
 *
 * Le lot 3 (#9896) a trouve `bulk_payment_claims` avec `ENABLE` + `FORCE ROW
 * LEVEL SECURITY` mais **aucune politique**, alors que la migration #9879
 * etait enregistree comme passee. Cause mesuree dans
 * `shared_tenants.migrations` :
 *
 *     batch 2 : 2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims
 *     batch 3 : 2026_11_24_000100_9874_create_bulk_payment_claims_table
 *
 * La migration RLS a tourne AVANT la creation de la table. Son garde
 * `if (! schemaTableExists(...)) { return; }` l'a ignoree, et Laravel l'a
 * marquee « Ran » definitivement : `migrate` ne rejoue jamais une migration
 * enregistree.
 *
 * ## Pourquoi c'est structurel, pas accidentel
 *
 * Mesure sur ce depot : **350 migrations** portent un early-return sur
 * `schemaTableExists`, dont **338** font du DDL (colonne, index, contrainte,
 * politique). Chacune peut se terminer sans avoir rien fait, en laissant un
 * `migrate:status` vert. Un garde d'existence est legitime (il tolere un ordre
 * de deploiement) ; ce qui ne l'est pas, c'est son **silence**.
 *
 * ## Ce que la sonde detecte
 *
 * Elle ne peut pas deviner l'intention d'une migration. Elle detecte donc le
 * symptome mesurable et non ambigu : **une migration dont l'horodatage est
 * anterieur a celui d'une migration executee dans un batch ulterieur**.
 *
 *     batch 2 : 2026_11_24_000200_...   <- horodatage PLUS TARDIF
 *     batch 3 : 2026_11_24_000100_...   <- execute APRES, horodatage plus tot
 *
 * C'est exactement la signature du defaut #9896 : une migration a ete
 * introduite apres coup avec un horodatage anterieur, donc elle a tourne avant
 * la table dont elle dependait. Sur une base saine, l'ordre des batches suit
 * l'ordre des horodatages.
 *
 * ## Codes de sortie
 *
 *   0 — aucun desordre detecte
 *   1 — au moins une migration a tourne avant une migration plus ancienne
 *   2 — table `migrations` introuvable (base non migree)
 *   3 — environnement non-PostgreSQL
 *
 * `--strict` transforme le code 1 en echec de pipeline de deploiement.
 */
final class MigrationsSkippedEffectsCommand extends Command
{
    protected $signature = 'migrations:skipped-effects
                            {--json : Sortie machine (CI, scripts)}
                            {--strict : Sortir en erreur si un desordre est detecte}
                            {--limit=20 : Nombre de cas affiches}';

    protected $description = 'Detecte les migrations executees dans un ordre incompatible avec leur horodatage (signature du defaut #9896)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('migrations:skipped-effects : sonde PostgreSQL uniquement (driver courant : '.DB::getDriverName().').');

            return 3;
        }

        $schema = $this->migrationsSchema();

        if ($schema === null) {
            $this->error('migrations:skipped-effects : table `migrations` introuvable — la base n\'est pas migree.');

            return 2;
        }

        $rows = $this->outOfOrder($schema);
        $limit = max(1, (int) $this->option('limit'));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'schema' => $schema,
                'out_of_order' => $rows,
                'count' => count($rows),
                'verdict' => $rows === []
                    ? 'aucun desordre : l\'ordre des batches suit l\'ordre des horodatages'
                    : 'desordre detecte : une migration a tourne avant une migration plus ancienne',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->option('strict') && $rows !== [] ? 1 : 0;
        }

        $this->newLine();
        $this->line('  <options=bold>Migrations — ordre d\'execution vs horodatage</>');
        $this->line('  Schema des migrations : '.$schema);
        $this->newLine();

        if ($rows === []) {
            $this->info('  ✅ Aucun desordre : l\'ordre des batches suit l\'ordre des horodatages.');
            $this->newLine();

            return 0;
        }

        $this->warn('  ⚠️  '.count($rows).' migration(s) ont tourne dans un batch ulterieur a une migration plus ancienne.');
        $this->line('     C\'est la signature du defaut #9896 : une migration introduite apres coup');
        $this->line('     avec un horodatage anterieur a tourne avant la table dont elle dependait.');
        $this->line('     Son garde `schemaTableExists` a pu la sauter en silence.');
        $this->newLine();

        $this->table(
            ['Migration (plus ancienne)', 'Batch', 'Executee apres', 'Batch'],
            array_map(static fn (array $r): array => [
                $r['older'],
                $r['older_batch'],
                $r['newer'],
                $r['newer_batch'],
            ], array_slice($rows, 0, $limit)),
        );

        if (count($rows) > $limit) {
            $this->line('  … et '.(count($rows) - $limit).' autre(s). Utiliser --limit pour en afficher davantage.');
        }

        $this->newLine();
        $this->line('  Verifier chaque cas : la migration a-t-elle produit son effet attendu ?');
        $this->line('  Si non, une migration de reparation idempotente est necessaire (modele : #9896).');
        $this->newLine();

        return $this->option('strict') ? 1 : 0;
    }

    /**
     * Schema qui porte la table `migrations` (search_path, puis public).
     */
    private function migrationsSchema(): ?string
    {
        try {
            /** @var object{s: string}|null $row */
            $row = DB::selectOne(
                "SELECT table_schema AS s
                   FROM information_schema.tables
                  WHERE table_name = 'migrations'
                    AND table_schema = ANY (current_schemas(false))
                  ORDER BY array_position(current_schemas(false), table_schema)
                  LIMIT 1"
            );
        } catch (Throwable) {
            return null;
        }

        return $row === null ? null : (string) $row->s;
    }

    /**
     * Paires (migration ancienne, migration plus recente executee avant elle).
     *
     * La logique vit dans `MigrationOrderInspector` : elle est pure, donc
     * testable sans base migree (la base de test de ce depot n'a pas de table
     * `migrations` persistante).
     *
     * @return list<array{older: string, older_batch: int, newer: string, newer_batch: int}>
     */
    private function outOfOrder(string $schema): array
    {
        /** @var list<object{migration: string, batch: int}> $rows */
        $rows = DB::select('SELECT migration, batch FROM '.$schema.'.migrations ORDER BY batch, migration');

        return MigrationOrderInspector::outOfOrder($rows);
    }
}
