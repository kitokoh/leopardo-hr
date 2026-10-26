<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-1 (#8512) — socle SEO du module SearchIntelligence.
 *
 * Ces tables vivent dans le schéma `public` et ne sont PAS scopées à une
 * entreprise : la recherche (SEO/SEA/AEO/GEO) est pilotée depuis la console
 * plateforme, par des collaborateurs internes portant la permission
 * `search_intel.view` / `search_intel.manage`. Il n'y a donc volontairement
 * aucune colonne `company_id` ici — un `tenant` sanctum n'a aucun chemin
 * d'écriture vers ces agrégats.
 *
 * Réversibilité : `down()` supprime les tables dans l'ordre inverse des
 * dépendances (enfants d'abord), sans `CASCADE` implicite — la migration
 * reste jouable en local comme en CI.
 *
 * @see docs/architecture/bounded-context-registry.json (entrée `search-intelligence`)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pages observées du site (une ligne = une URL unique du parc).
        // `url_hash` (sha256 de l'URL absolue) sert de clé d'unicité : les URL
        // dépassent la taille d'un index btree et deux URLs ne diffèrent
        // parfois que par la casse ou un paramètre.
        if (! Schema::hasTable('seo_pages')) {
            Schema::create('seo_pages', function (Blueprint $table) {
                $table->id();
                $table->text('url');
                $table->string('url_hash', 64)->unique();
                $table->string('host', 255)->index();
                $table->text('path');
                $table->string('title')->nullable();
                $table->text('meta_description')->nullable();
                $table->unsignedSmallInteger('http_status')->default(200);
                $table->unsignedInteger('word_count')->default(0);
                $table->text('canonical_url')->nullable();
                $table->string('robots', 120)->nullable();
                $table->boolean('has_schema')->default(false);
                $table->boolean('is_indexable')->default(true);
                $table->timestamp('last_scanned_at')->nullable();
                $table->timestamps();
            });
        }

        // Anomalies détectées par le scanner technique (GSI-2).
        // Une anomalie est un FAIT daté rattaché à une page : elle garde son
        // historique (detected_at / resolved_at) au lieu d'être écrasée, pour
        // que le workflow de traitement reste auditable.
        if (! Schema::hasTable('seo_issues')) {
            Schema::create('seo_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
                $table->string('type', 60);
                $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('status', ['open', 'acknowledged', 'resolved', 'ignored'])->default('open');
                $table->string('title');
                $table->text('detail')->nullable();
                $table->timestamp('detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity']);
                $table->index('type');
            });
        }

        // Registre des mots-clés cibles (GSI-2). L'unicité est portée par
        // (phrase, locale) : « plombier paris » en fr et en en sont deux
        // cibles distinctes, mais un doublon exact est un défaut de saisie.
        if (! Schema::hasTable('keywords')) {
            Schema::create('keywords', function (Blueprint $table) {
                $table->id();
                $table->string('phrase');
                $table->string('locale', 10)->default('fr');
                $table->enum('intent', ['informational', 'navigational', 'transactional', 'commercial'])->default('informational');
                $table->unsignedSmallInteger('priority')->default(3);
                $table->unsignedInteger('volume')->nullable();
                $table->unsignedSmallInteger('difficulty')->nullable();
                $table->unsignedInteger('current_rank')->nullable();
                $table->text('target_url')->nullable();
                $table->timestamps();

                $table->unique(['phrase', 'locale']);
            });
        }

        // Intégrations externes (GSI-8). `status` vaut `not_connected` par
        // défaut : l'absence de connecteur est un ÉTAT LÉGITIME et affiché
        // comme tel (« non connecté »), jamais simulé par des métriques
        // inventées. Les secrets sont chiffrés côté application et ne sont
        // jamais sérialisés en clair par l'API.
        if (! Schema::hasTable('integrations')) {
            Schema::create('integrations', function (Blueprint $table) {
                $table->id();
                $table->enum('provider', ['gsc', 'ga4', 'google_ads', 'ms_ads'])->unique();
                $table->enum('status', ['not_connected', 'connected', 'error'])->default('not_connected');
                $table->text('credentials_encrypted')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('seo_issues');
        Schema::dropIfExists('seo_pages');
    }
};
