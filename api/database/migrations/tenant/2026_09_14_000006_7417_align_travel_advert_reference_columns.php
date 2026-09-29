<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * #7417 — NEUTRALISÉE (no-op volontaire, #8207 / BOS-018).
 *
 * Ce correctif (alignement du référentiel annonces Travel : `name` → `label`,
 * `type_id` → `advert_type_id`, `position_id` → `advert_position_id`) a été
 * commité DEUX FOIS, ici et dans
 * `2026_09_14_000007_7420_align_travel_advert_reference_columns.php` —
 * même table `RENAMES`, mêmes gardes idempotentes.
 *
 * La génération `000007_7420` est conservée comme correctif CANONIQUE
 * (version aboutie : `strict_types`, documentation complète). Les deux étant
 * idempotents, l'état appliqué en production est identique.
 *
 * Ce fichier est conservé en coquille vide plutôt que supprimé : sur tout
 * environnement où `2026_09_14_000006_7417_*` figure déjà dans la table
 * `migrations`, Laravel résout chaque entrée par son fichier au rollback —
 * un fichier manquant fait échouer `migrate:rollback` du batch
 * (« Migration not found »). up() et down() sont donc volontairement des
 * no-op : la chaîne de rollback reste intacte et l'état final est porté par
 * `000007_7420` seule.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op — correctif canonique :
        // 2026_09_14_000007_7420_align_travel_advert_reference_columns.php
    }

    public function down(): void
    {
        // No-op — symétrique (les renommages inverses sont assurés par la
        // génération canonique 000007_7420).
    }
};
