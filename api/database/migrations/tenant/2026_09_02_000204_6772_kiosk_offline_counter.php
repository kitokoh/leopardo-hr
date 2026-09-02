<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BIO-007 (#6772) — synchronisation offline kiosque bornée et vérifiable.
 *
 * `acked_event_counter` : dernier compteur monotone d'événements offline
 * acquitté par le serveur pour CE kiosque. Le client signe chaque batch
 * (HMAC avec son sync_token, enveloppe `device_state`) ; le serveur rejette
 * tout batch dont le compteur n'est pas strictement supérieur (rejeu → 409)
 * et persiste l'acquittement après traitement. Zéro doublon de présence,
 * mode offline borné (fenêtre d'ancienneté configurée côté serveur).
 *
 * Convention #1613 : ALTER résolu via `resolveTableSchema()` (jamais de
 * appel Schéma-table au nom nu — piège F-17 du search_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('attendance_kiosks');

        if ($schema === null || schemaHasColumn('attendance_kiosks', 'acked_event_counter')) {
            return;
        }

        DB::statement(
            "ALTER TABLE {$schema}.attendance_kiosks ADD COLUMN acked_event_counter BIGINT NOT NULL DEFAULT 0"
        );
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_kiosks');

        if ($schema === null || ! schemaHasColumn('attendance_kiosks', 'acked_event_counter')) {
            return;
        }

        DB::statement(
            "ALTER TABLE {$schema}.attendance_kiosks DROP COLUMN acked_event_counter"
        );
    }
};
