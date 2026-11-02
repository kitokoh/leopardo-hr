<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3 (#9105) · Rattachement d'une commande / réservation à un
 * client de la passerelle publique.
 *
 * ## Pourquoi cette colonne est indispensable
 *
 * `public_activity_index` (schéma `public`) ne contient que des références. Pour
 * afficher « votre commande RST-123 est en préparation », le provider de la
 * verticale doit relire la ligne RÉELLE dans le schéma du tenant — et pour cela
 * il doit pouvoir répondre à « cette commande appartient-elle à CE client ? ».
 *
 * Au HEAD, `restaurant_orders` et `restaurant_reservations` n'ont aucun lien
 * vers un `users.id` : `customer_contact_id` pointe vers un contact commercial
 * du tenant, pas vers un compte plateforme. Sans cette colonne, le provider
 * n'aurait d'autre choix que de faire confiance à l'index — c'est-à-dire
 * d'afficher une commande à quiconque connaît sa référence. C'est exactement ce
 * que l'invariant « cette table n'autorise rien » interdit.
 *
 * ## Additive et rétro-compatible
 *
 * Colonne NULLABLE : toutes les lignes existantes restent valides et
 * continuent de fonctionner (commandes passées sans compte, kiosque, POS).
 * Aucune donnée n'est réécrite, aucune contrainte n'est ajoutée aux lignes
 * historiques.
 *
 * Même patron que `conversation_threads.public_client_id` (#8760) : le client
 * public est un `users.id` (guard `user_api`), pas un employé.
 *
 * Conventions : `docs/architecture/MIGRATIONS_CONVENTIONS.md` — `strict_types`,
 * `return new class`, réentrance par `schemaTableExists()` / `schemaHasColumn()`,
 * `down()` limité à ce que `up()` a créé, index tenant-first.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        foreach (['restaurant_orders', 'restaurant_reservations'] as $tableName) {
            if (! schemaTableExists($tableName) || schemaHasColumn($tableName, 'public_client_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('public_client_id')->nullable()->after('customer_contact_id');
            });

            // Index tenant-first : la lecture est TOUJOURS
            // `where company_id = ? and public_client_id = ?`.
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index(
                    ['company_id', 'public_client_id'],
                    $tableName.'_company_public_client_index',
                );
            });

            DB::statement(sprintf(
                "COMMENT ON COLUMN %s.public_client_id IS '[COULOIR16 M3 #9105] Client de la passerelle publique (users.id). NULL pour les commandes POS, kiosque et sans compte.'",
                $tableName,
            ));
        }
    }

    public function down(): void
    {
        foreach (['restaurant_orders', 'restaurant_reservations'] as $tableName) {
            if (! schemaTableExists($tableName) || ! schemaHasColumn($tableName, 'public_client_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_company_public_client_index');
                $table->dropColumn('public_client_id');
            });
        }
    }
};
