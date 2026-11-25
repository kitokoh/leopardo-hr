<?php

declare(strict_types=1);

namespace App\Database\Migrations;

/**
 * #9923 — detection des migrations executees dans un ordre incompatible avec
 * leur horodatage.
 *
 * ## Pourquoi cette classe est separee de la commande
 *
 * La logique est pure : elle prend des lignes `(migration, batch)` et rend les
 * paires incoherentes. La garder dans la commande la rendait intestable sans
 * une base migree — or la base de test de ce depot n'a pas de table
 * `migrations` persistante (elle est creee et detruite par `RefreshDatabase`).
 * Un test qui depend de l'etat de la base ne prouve rien sur la logique.
 *
 * ## Le symptome detecte
 *
 * Une migration dont l'horodatage est **anterieur** a celui d'une migration
 * executee dans un **batch ulterieur** :
 *
 *     batch 2 : 2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims
 *     batch 3 : 2026_11_24_000100_9874_create_bulk_payment_claims_table
 *
 * C'est la signature exacte du defaut #9896 : la migration RLS a tourne avant
 * la creation de sa table, son garde `schemaTableExists` l'a sautee en
 * silence, et Laravel l'a marquee « Ran » definitivement.
 *
 * ## Ce que la classe ne pretend pas faire
 *
 * Elle ne devine pas l'intention d'une migration. Un desordre d'ordre n'est
 * pas forcement un bug (une migration peut legitimement ne dependre de rien).
 * C'est un **signal a verifier**, pas un verdict — d'ou le mode non-strict par
 * defaut dans la commande.
 */
final class MigrationOrderInspector
{
    /**
     * Paires (migration ancienne, migration plus recente executee avant elle).
     *
     * @param  iterable<object{migration: string, batch: int}|array{migration: string, batch: int}>  $rows
     * @return list<array{older: string, older_batch: int, newer: string, newer_batch: int}>
     */
    public static function outOfOrder(iterable $rows): array
    {
        $parsed = [];

        foreach ($rows as $row) {
            $migration = is_array($row) ? (string) $row['migration'] : (string) $row->migration;
            $batch = is_array($row) ? (int) $row['batch'] : (int) $row->batch;
            $ts = self::timestampOf($migration);

            if ($ts === null) {
                continue;
            }

            $parsed[] = ['migration' => $migration, 'batch' => $batch, 'ts' => $ts];
        }

        $out = [];

        foreach ($parsed as $a) {
            foreach ($parsed as $b) {
                if ($b['batch'] > $a['batch'] && $b['ts'] < $a['ts']) {
                    $out[] = [
                        'older' => $b['migration'],
                        'older_batch' => $b['batch'],
                        'newer' => $a['migration'],
                        'newer_batch' => $a['batch'],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Prefixe `YYYY_MM_DD_HHMMSS` converti en entier comparable, ou null.
     *
     * Une migration sans prefixe exploitable est ignoree : on ne peut pas la
     * comparer, et l'inventer produirait un faux positif.
     */
    public static function timestampOf(string $migration): ?int
    {
        if (! preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})_/', $migration, $m)) {
            return null;
        }

        return (int) ($m[1].$m[2].$m[3].$m[4]);
    }
}
