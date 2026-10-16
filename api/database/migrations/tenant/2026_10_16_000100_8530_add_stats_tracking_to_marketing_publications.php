<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8530 — Statistiques REELLES des plateformes (Marketing OS, sections A et G).
 *
 * Ajoute a `marketing_publications` les trois colonnes necessaires pour
 * remonter des performances MESUREES par l'API d'une plateforme, sans jamais
 * inventer de valeur :
 *
 *   - `external_post_id` : identifiant du post cote plateforme (ou cote
 *     agregateur), indispensable pour interroger l'API de statistiques. Il
 *     est distinct de `published_url` (lien public) et n'est renseigne que
 *     lorsqu'une publication a REELLEMENT ete executee par une API.
 *   - `stats_fetched_at` : date de la derniere tentative de recuperation.
 *     Permet de distinguer « jamais interroge » de « interroge, rien recu ».
 *   - `stats_error` : motif de l'indisponibilite (plateforme sans API de
 *     statistiques, connecteur absent, erreur API). L'interface peut ainsi
 *     expliquer POURQUOI la donnee manque au lieu d'afficher un vide ambigu.
 *
 * `stats` et `stats_source` existent deja (migration #8528) : `stats_source`
 * vaut `platform` uniquement quand les valeurs proviennent d'un appel API
 * reel, `manual` quand un humain les a saisies, `unavailable` sinon. Aucune
 * estimation n'est jamais presentee comme un fait.
 *
 * Additive (colonnes nullables, aucun backfill : les publications anterieures
 * gardent des colonnes NULL et restent simplement « non mesurees » —
 * fail-closed), idempotente, `down()` limite aux colonnes ajoutees ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('marketing_publications')) {
            return;
        }

        if (! schemaHasColumn('marketing_publications', 'external_post_id')) {
            Schema::table('marketing_publications', function (Blueprint $table): void {
                $table->string('external_post_id', 191)->nullable();
            });
        }

        if (! schemaHasColumn('marketing_publications', 'stats_fetched_at')) {
            Schema::table('marketing_publications', function (Blueprint $table): void {
                $table->timestampTz('stats_fetched_at')->nullable();
            });
        }

        if (! schemaHasColumn('marketing_publications', 'stats_error')) {
            Schema::table('marketing_publications', function (Blueprint $table): void {
                $table->text('stats_error')->nullable();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN marketing_publications.external_post_id IS 'Identifiant du post cote plateforme/agregateur, requis pour interroger l API de statistiques (#8530).';") ;
            DB::statement("COMMENT ON COLUMN marketing_publications.stats_fetched_at IS 'Date de la derniere tentative de recuperation des statistiques (#8530) — distingue jamais interroge de interroge sans donnee.';");
            DB::statement("COMMENT ON COLUMN marketing_publications.stats_error IS 'Motif de l indisponibilite des statistiques (#8530) : plateforme sans API, connecteur absent ou erreur API.';");
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('marketing_publications')) {
            return;
        }

        foreach (['stats_error', 'stats_fetched_at', 'external_post_id'] as $column) {
            if (schemaHasColumn('marketing_publications', $column)) {
                Schema::table('marketing_publications', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
