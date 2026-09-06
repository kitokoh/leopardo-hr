<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #6549 — le statut `pending` manque à la contrainte CHECK de
 * `invoices.status`.
 *
 * `billing:generate-invoices` crée des factures au statut `pending` (émise,
 * en attente de paiement) — statut documenté par l'OpenAPI et passé en
 * lecture par `InvoiceResource`. La migration d'origine (enum Laravel →
 * CHECK `invoices_status_check`) n'autorise que
 * draft/sent/paid/overdue/cancelled : toute insertion `pending` levait une
 * violation 23514 avalée par la commande (0 facture générée, erreur
 * silencieuse).
 *
 * Correctif : recréer la contrainte en y ajoutant `pending` (additif,
 * idempotent).
 *
 * Canonique depuis le dédoublonnage #6924 (2026-09-06) : les jumelles
 * 2026_08_30_000952/001549_6248 (résidus de merges union) ont été supprimées
 * du dépôt — déjà enregistrées sur les envs existants, leur rejeu n'est pas
 * nécessaire (DROP IF EXISTS idempotent) ; un env neuf n'exécute que ce fichier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('invoices')) {
            return;
        }

        // Reconstruction idempotente : la contrainte est DROP puis recréée
        // avec le même nom — un re-run ne lève rien.
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
        DB::statement("
            ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
            CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled', 'pending'))
        ");
    }

    public function down(): void
    {
        if (! schemaTableExists('invoices')) {
            return;
        }

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
        DB::statement("
            ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
            CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled'))
        ");
    }
};
