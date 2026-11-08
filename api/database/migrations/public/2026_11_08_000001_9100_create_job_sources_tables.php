<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-10 RECRUITMENT — sources d'offres d'emploi externes (agrégateurs / job
 * boards) et offres importées.
 *
 * Ces tables vivent dans le schéma `public` et ne sont PAS scopées à une
 * entreprise : une offre importée depuis un agrégateur n'appartient à aucun
 * tenant, elle est publiée par la PLATEFORME. La placer dans un schéma tenant
 * obligerait à choisir un tenant arbitraire (fuite de données entre clients)
 * ou à créer un tenant « plateforme » fictif (contournement de la
 * constitution §II). Le portail public agrège donc deux sources : les
 * `job_postings` du tenant et les `external_job_offers` de la plateforme.
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §3.1, §4.1
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Un connecteur d'agrégateur. `status` vaut `not_connected` par défaut :
        // l'absence de connecteur est un ÉTAT LÉGITIME et affiché comme tel
        // (« non connecté »), jamais simulé par des offres inventées. Les
        // identifiants sont chiffrés côté application (cast `encrypted`) et ne
        // sont jamais sérialisés en clair par l'API.
        if (! $this->publicTableExists('job_sources')) {
            Schema::create('job_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 40)->unique();
                $table->string('label', 120);
                $table->enum('status', ['not_connected', 'connected', 'error'])->default('not_connected');
                $table->text('credentials_encrypted')->nullable();
                $table->jsonb('config')->nullable();
                $table->timestampTz('last_synced_at')->nullable();
                $table->string('last_error', 500)->nullable();
                $table->unsignedInteger('offers_imported')->default(0);
                $table->timestampsTz();
            });
        }

        // Une offre importée. La clé métier `(source_id, external_id)` est
        // UNIQUE : c'est elle qui rend la synchronisation idempotente — deux
        // exécutions du job convergent vers le même état au lieu de dupliquer
        // les offres. `raw_payload` conserve la charge utile d'origine pour la
        // traçabilité (et pour rejouer un mapping corrigé sans re-fetch).
        if (! $this->publicTableExists('external_job_offers')) {
            Schema::create('external_job_offers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('source_id');
                $table->foreign('source_id')->references('id')->on('job_sources')->cascadeOnDelete();
                $table->string('external_id', 191);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('company_name', 200);
                $table->string('location', 200)->nullable();
                $table->string('country', 2)->nullable();
                $table->enum('remote_policy', ['onsite', 'hybrid', 'remote'])->default('onsite');
                $table->enum('contract_type', ['cdi', 'cdd', 'stage', 'freelance'])->default('cdi');
                $table->decimal('salary_min', 12, 2)->nullable();
                $table->decimal('salary_max', 12, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('apply_url', 500);
                $table->timestampTz('published_at')->nullable();
                $table->timestampTz('expires_at')->nullable();
                $table->enum('status', ['published', 'closed'])->default('published');
                $table->jsonb('raw_payload')->nullable();
                $table->timestampsTz();

                $table->unique(['source_id', 'external_id']);
                $table->index(['status', 'published_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_job_offers');
        Schema::dropIfExists('job_sources');
    }

    /**
     * `Schema::hasTable()` resolve via `current_schema()`, qui vaut
     * `shared_tenants` sous le search_path de l'application. Un garde écrit
     * avec `Schema::hasTable()` sur une table `public` renverrait donc
     * TOUJOURS `false` : la migration rejouerait `CREATE TABLE` et échouerait
     * si la table existe déjà. On interroge explicitement `public`.
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
};
