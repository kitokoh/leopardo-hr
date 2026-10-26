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
        if ($this->publicTableExists('seo_issues')) {
            Schema::table('seo_issues', function (Blueprint $table): void {
                if (! $this->publicColumnExists('seo_issues', 'recommendation')) {
                    $table->text('recommendation')->nullable();
                }
                if (! $this->publicColumnExists('seo_issues', 'dismissed_reason')) {
                    $table->text('dismissed_reason')->nullable();
                }
                if (! $this->publicColumnExists('seo_issues', 'applied_at')) {
                    $table->timestamp('applied_at')->nullable();
                }
                if (! $this->publicColumnExists('seo_issues', 'verified_at')) {
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

        if ($this->publicTableExists('keywords')) {
            Schema::table('keywords', function (Blueprint $table): void {
                if (! $this->publicColumnExists('keywords', 'target_page_id')) {
                    $table->unsignedBigInteger('target_page_id')->nullable();
                }
                if (! $this->publicColumnExists('keywords', 'impressions')) {
                    $table->unsignedInteger('impressions')->nullable();
                }
                if (! $this->publicColumnExists('keywords', 'clicks')) {
                    $table->unsignedInteger('clicks')->nullable();
                }
                if (! $this->publicColumnExists('keywords', 'data_source')) {
                    // D'où vient le chiffre : relevé manuel, ou connecteur réel.
                    $table->enum('data_source', ['manual', 'integration'])->default('manual');
                }
                if (! $this->publicColumnExists('keywords', 'last_measured_at')) {
                    $table->timestamp('last_measured_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->publicTableExists('keywords')) {
            Schema::table('keywords', function (Blueprint $table): void {
                foreach (['last_measured_at', 'data_source', 'clicks', 'impressions', 'target_page_id'] as $column) {
                    if ($this->publicColumnExists('keywords', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if ($this->publicTableExists('seo_issues')) {
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_dismissed_requires_reason');
            DB::statement('ALTER TABLE seo_issues DROP CONSTRAINT IF EXISTS seo_issues_status_check');

            Schema::table('seo_issues', function (Blueprint $table): void {
                foreach (['verified_at', 'applied_at', 'dismissed_reason', 'recommendation'] as $column) {
                    if ($this->publicColumnExists('seo_issues', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * `Schema::hasTable()` / `hasColumn()` résolvent via `current_schema()`,
     * qui vaut `shared_tenants` hors du harnais de test : un garde sur une
     * table `public` renvoie alors systématiquement `false` et la migration
     * saute ses ALTER sans rien signaler. On interroge `public` explicitement.
     */
    private function publicTableExists(string $table): bool
    {
        /** @var object{exists: bool}|null $row */
        $row = DB::selectOne(
            'select exists (select 1 from information_schema.tables where table_schema = ? and table_name = ?) as exists',
            ['public', $table],
        );

        return $row !== null && (bool) $row->exists;
    }

    private function publicColumnExists(string $table, string $column): bool
    {
        /** @var object{exists: bool}|null $row */
        $row = DB::selectOne(
            'select exists (select 1 from information_schema.columns '
            .'where table_schema = ? and table_name = ? and column_name = ?) as exists',
            ['public', $table, $column],
        );

        return $row !== null && (bool) $row->exists;
    }
};
