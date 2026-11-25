<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * #9923 — base de migration dont les gardes d'existence sont BRUYANTS.
 *
 * ## Le probleme
 *
 * 350 migrations de ce depot portent un early-return sur
 * `schemaTableExists()`, dont 338 font du DDL. Le garde est legitime : il
 * tolere qu'une migration tourne avant la creation de sa table (ordre de
 * deploiement, restauration partielle, `migrate --path` selectif).
 *
 * Ce qui ne l'est pas, c'est son **silence**. Le defaut #9896 est ne
 * exactement de la :
 *
 *     if (! schemaTableExists('bulk_payment_claims')) {
 *         return;   // <- rien dans les logs, migration marquee « Ran »
 *     }
 *
 * Resultat : `migrate:status` vert, `ENABLE`/`FORCE` poses, **aucune
 * politique RLS**. La protection etait annoncee et absente.
 *
 * ## Ce que cette classe apporte
 *
 * `skipBecauseTableMissing()` remplace le `return` muet par un avertissement
 * explicite (log + sortie console quand elle existe). Une migration qui ne
 * fait rien doit le dire — c'est la seule facon de distinguer « deja fait »
 * de « jamais fait ».
 *
 * ## Pourquoi une classe de base et pas une reecriture des 350 migrations
 *
 * Reecrire 350 fichiers pour y ajouter un `Log::warning()` serait un diff de
 * 350 fichiers, impossible a relire, et qui toucherait des migrations deja
 * executees en production (donc sans effet, mais avec un risque de conflit
 * sur les branches ouvertes). La classe de base est **opt-in** : les
 * migrations nouvelles et celles qu'on repare l'utilisent, les anciennes
 * restent telles quelles. La sonde `migrations:skipped-effects` couvre
 * l'existant.
 *
 * ## Usage
 *
 *     return new class extends GuardedMigration
 *     {
 *         public function up(): void
 *         {
 *             if (! schemaTableExists('employees')) {
 *                 $this->skipBecauseTableMissing('employees');
 *
 *                 return;
 *             }
 *
 *             // ... DDL
 *         }
 *     };
 */
abstract class GuardedMigration extends Migration
{
    /**
     * Signale qu'une migration n'a rien fait parce que sa table n'existe pas.
     *
     * Le message nomme la migration, la table et la consequence : sans cela,
     * un lecteur de log ne peut pas savoir si l'effet est absent ou deja
     * present.
     */
    protected function skipBecauseTableMissing(string $table, string $consequence = 'DDL non applique'): void
    {
        $migration = static::class;

        Log::warning('Migration sautee : table absente (#9923)', [
            'migration' => $migration,
            'table' => $table,
            'consequence' => $consequence,
        ]);

        // `$this->output` n'existe que pendant `migrate` (pas en test unitaire
        // ni en `migrate:fresh` silencieux) : on ne suppose pas sa presence.
        if (property_exists($this, 'output') && $this->output !== null) {
            $this->output->writeln(sprintf(
                '  <comment>⚠️  %s : table `%s` absente — %s.</comment>',
                $migration,
                $table,
                $consequence,
            ));
        }
    }

    /**
     * Signale qu'une migration n'a rien fait parce que sa colonne existe deja.
     *
     * Cas distinct du precedent : ici l'effet est probablement DEJA present
     * (idempotence), ce qui est normal. On le trace en `info`, pas en
     * `warning` — confondre les deux noierait le vrai signal.
     */
    protected function skipBecauseColumnExists(string $table, string $column): void
    {
        Log::info('Migration idempotente : colonne deja presente (#9923)', [
            'migration' => static::class,
            'table' => $table,
            'column' => $column,
        ]);
    }
}
