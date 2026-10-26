<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-2 (#8513) — enrichissements demandés par la spec SEO.
 *
 * Cette migration est ADDITIVE et idempotente : elle s'applique à un schéma
 * GSI-1 déjà en place, sans recréer les tables. Chaque colonne est ajoutée
 * conditionnellement (`hasColumn`) pour rester rejouable.
 *
 * Deux ajouts fonctionnels :
 *
 *  1. **`seo_issues` : le workflow de traitement.** Le statut GSI-1 était un
 *     simple `open/acknowledged/resolved/ignored` ; le flux réel exige
 *     `detected → recommended → approved → applied → verified` (+ `dismissed`
 *     avec justification). On ajoute les colonnes qui portent ce workflow
 *     (correction proposée, motif d'écartement, horodatages) et on élargit
 *     l'énumération.
 *
 *  2. **`keywords` : les mesures réelles.** Volume/position existaient, mais
 *     sans `data_source` ni `last_measured_at` un chiffre ne dit pas d'où il
 *     vient ni quand il a été relevé — exactement ce que la spec interdit.
 *
 * PostgreSQL : les colonnes `enum` de Laravel sont des `varchar` + contrainte
 * CHECK, on remplace donc la contrainte plutôt que le type.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_issues')) {
            Schema::table('seo_issues', function (Blueprint $table): void {
                if (! Schema::hasColumn('seo_issues', 'recommendation')) {
                    $table->text('recommendation')->nullable();
                }
                if (! Schema::hasColumn('seo_issues', 'dismissed_reason')) {
                    $table->text('dismissed_reason')->nullable();
                }
                if (! Schema::hasColumn('seo_issues', 'applied_at')) {
                    $table->timestamp('applied_at')->nullable();
                }
                if (! Schema::hasColumn('seo_issues', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
            });

            // Élargissement du domaine de valeurs du statut. La contrainte est
            // recréée depuis zéro (DROP IF EXISTS puis ADD) pour que la
            // migration reste rejouable sur une base à l'ancien jeu.
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_status_check');
            DB::statement(
                "ALTER TABLE seo_issues ADD CONSTRAINT seo_issues_status_check CHECK (status IN ('detected', 'recommended', 'approved', 'applied', 'verified', 'dismissed'))"
            );

            // Le nouveau socle part de `detected` ; l'ancien défaut `open`
            // n'existe plus dans l'énumération.
            DB::statement("ALTER TABLE seo_issues ALTER COLUMN status SET DEFAULT 'detected'");

            // Un écartement sans motif est un abandon déguisé : la base refuse.
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_dismissed_requires_reason');
            DB::statement(
                "ALTER TABLE seo_issues ADD CONSTRAINT seo_issues_dismissed_requires_reason CHECK (status <> 'dismissed' OR dismissed_reason IS NOT NULL)"
            );
        }

        if (Schema::hasTable('keywords')) {
            Schema::table('keywords', function (Blueprint $table): void {
                if (! Schema::hasColumn('keywords', 'target_page_id')) {
                    $table->unsignedBigInteger('target_page_id')->nullable();
                }
                if (! Schema::hasColumn('keywords', 'impressions')) {
                    $table->unsignedInteger('impressions')->nullable();
                }
                if (! Schema::hasColumn('keywords', 'clicks')) {
                    $table->unsignedInteger('clicks')->nullable();
                }
                if (! Schema::hasColumn('keywords', 'data_source')) {
                    // D'où vient le chiffre : relevé manuel, ou connecteur réel.
                    $table->enum('data_source', ['manual', 'integration'])->default('manual');
                }
                if (! Schema::hasColumn('keywords', 'last_measured_at')) {
                    $table->timestamp('last_measured_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('keywords')) {
            Schema::table('keywords', function (Blueprint $table): void {
                foreach (['last_measured_at', 'data_source', 'clicks', 'impressions', 'target_page_id'] as $column) {
                    if (Schema::hasColumn('keywords', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('seo_issues')) {
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_dismissed_requires_reason');
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_status_check');

            Schema::table('seo_issues', function (Blueprint $table): void {
                foreach (['verified_at', 'applied_at', 'dismissed_reason', 'recommendation'] as $column) {
                    if (Schema::hasColumn('seo_issues', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
