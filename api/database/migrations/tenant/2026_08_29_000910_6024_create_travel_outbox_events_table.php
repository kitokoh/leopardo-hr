<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6024 (TRAVEL-211) — Outbox des événements TravelAgency (tenant-scoped).
 *
 * Structure identique au pattern `crm_outbox_events` (#5741) : un effet
 * métier est d'abord PERSISTÉ dans cette table APRÈS le commit de la
 * transaction métier, puis consommé de façon asynchrone et idempotente. La
 * contrainte unique (company_id, idempotency_key) garantit zéro doublon
 * même en cas de rejeu. `payload_redacted` : jamais de secret/token/PII en
 * clair dans le payload persisté.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Issue #7452 — `travel_outbox_events` n'est déclarée qu'UNE fois, par
        // `2026_08_29_000610_6024_create_travel_outbox_events_table.php` (colonnes
        // strictement identiques). Cette génération ne crée plus la table : elle
        // n'était déjà jamais exécutée (garde `schemaTableExists()` + `return` avant
        // le `Schema::create`). Ses `DB::statement` (contrainte CHECK, commentaires)
        // restent volontairement hors service : les réactiver changerait un contrat
        // jamais appliqué en environnement réel.
    }

    public function down(): void
    {
        // #8207 (BOS-018) — no-op, symétrique du up() : cette génération ne
        // crée ni n'ajoute rien (la table appartient à
        // `2026_08_29_000610_6024_create_travel_outbox_events_table.php`).
        // La version précédente droppait les dix colonnes de la table — un
        // rollback du batch la laissait vivante mais vidée de son schéma,
        // la migration propriétaire restant « migrée ».
    }
};
