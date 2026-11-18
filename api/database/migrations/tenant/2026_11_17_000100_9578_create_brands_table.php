<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9578 (BC-41 BRANDING) — agrégat `Brand` : l'enseigne d'un tenant.
 *
 * ## Pourquoi une table neuve plutôt qu'étendre `companies.metadata.branding`
 *
 * Le branding actuel est un JSON libre dans `companies.metadata` : **une seule
 * marque par entreprise**, par construction. Un groupe qui exploite deux
 * enseignes doit créer deux tenants — donc deux jeux de données, deux
 * facturations, deux équipes. `brands` est multi-instances : `company_id` n'est
 * pas unique, `(company_id, slug)` l'est.
 *
 * ## Pourquoi pas `company_showcases` (BC-27)
 *
 * Sa clé d'unicité est `company_id` : une vitrine par tenant. Casser cette
 * contrainte en production, sur des données vivantes, pour un modèle dont les
 * états diffèrent (`suspended` n'existe pas côté Showcase), est un risque sans
 * contrepartie. Et Vitrine est hors périmètre de la mission.
 *
 * ## Invariants portés par le SCHÉMA, pas seulement par le code
 *
 * - **I1** une seule Brand par défaut par tenant → index UNIQUE partiel
 *   `WHERE is_default` (un index simple sur `is_default` n'empêcherait rien) ;
 * - **I2** slug unique par tenant → UNIQUE `(company_id, slug)` ;
 * - **I6** verticale ∈ registre fermé → CHECK sur la liste des valeurs ;
 * - **I7** un host = une Brand → UNIQUE partiel `WHERE host IS NOT NULL`
 *   (PostgreSQL autorise plusieurs NULL dans un UNIQUE, mais l'index partiel
 *   rend l'intention explicite et évite d'indexer les lignes sans domaine) ;
 * - **statut** ∈ allowlist → CHECK ;
 * - **suspension motivée** → CHECK `suspended_at IS NULL OR reason IS NOT NULL` ;
 * - **publiée ⇒ date de publication** → CHECK.
 *
 * ## Compatibilité
 *
 * Additive et idempotente. `down()` ne détruit que ce que `up()` a créé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('brands')) {
            Schema::create('brands', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                // Identité publique.
                $table->string('slug', 160);
                $table->string('name', 120);

                // Verticale métier — registre fermé (BrandVertical).
                $table->string('vertical', 40)->default('generic');

                // Publication.
                $table->string('status', 20)->default('draft');
                $table->timestampTz('published_at')->nullable();

                // Brand par défaut du tenant : celle qui sert une Page sans
                // Brand explicite. Une seule par tenant (index partiel).
                $table->boolean('is_default')->default(false);

                // Palette : { primary, accent, surface, on_primary }.
                // `on_primary` est STOCKÉ pour l'affichage rapide mais
                // RECALCULÉ à la lecture (BrandPalette::fromArray) : la
                // colonne n'est jamais la source de vérité du contraste.
                $table->jsonb('palette')->nullable();

                // Identité : { display_name, tagline, logo_url, favicon_url,
                // og_image_url }. `display_name` est requis pour publier.
                $table->jsonb('identity')->nullable();

                // Surface : { host, path_prefix, locales[], default_locale }.
                $table->jsonb('surface')->nullable();

                // Host dénormalisé pour l'index unique et la résolution par
                // Host (une requête indexée, pas un scan de JSON).
                $table->string('host', 190)->nullable();

                // Suspension / modération.
                $table->timestampTz('suspended_at')->nullable();
                $table->string('suspension_reason', 40)->nullable();

                $table->timestamps();

                // I2 — slug unique PAR tenant (deux tenants peuvent avoir
                // « chez-lea » ; c'est le host qui départage en public).
                $table->unique(['company_id', 'slug'], 'brands_company_slug_unique');

                $table->index('company_id', 'brands_company_index');
                $table->index(['status', 'vertical'], 'brands_status_vertical_index');
            });

            DB::statement(
                "COMMENT ON TABLE brands IS 'Enseigne d un tenant (BC-41, #9578) - multi-instances, slug unique par tenant, palette a contraste WCAG calcule serveur.'"
            );
        }

        // I1 — une seule Brand par défaut par tenant.
        // Index UNIQUE PARTIEL : un index simple sur `is_default` autoriserait
        // deux `true`, ce qui rendrait la résolution d'une Page sans Brand
        // ambiguë (deux candidats, ordre non déterministe).
        DB::statement('DROP INDEX IF EXISTS brands_one_default_per_company');
        DB::statement(
            'CREATE UNIQUE INDEX brands_one_default_per_company '
            .'ON brands (company_id) WHERE is_default = true'
        );

        // I7 — un host ne peut appartenir qu'à une Brand.
        DB::statement('DROP INDEX IF EXISTS brands_host_unique');
        DB::statement(
            'CREATE UNIQUE INDEX brands_host_unique '
            .'ON brands (host) WHERE host IS NOT NULL'
        );

        // I6 — verticale ∈ registre fermé. Une valeur inventée ne doit pas
        // entrer en base même par INSERT direct.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_vertical_allowed');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_vertical_allowed '
            ."CHECK (vertical IN ('generic', 'restaurant', 'retail', 'pharmacy', 'travel', 'vtc', 'fuel', 'hospitality', 'health', 'trade', 'ticketing'))"
        );

        // Statut ∈ allowlist.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_status_allowed');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_status_allowed '
            ."CHECK (status IN ('draft', 'published', 'suspended'))"
        );

        // Une Brand publiée a forcément une date de publication.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_published_requires_date');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_published_requires_date '
            ."CHECK (status <> 'published' OR published_at IS NOT NULL)"
        );

        // Symétriquement : une date de publication sur un brouillon signale un
        // bug d'écriture.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_draft_has_no_published_date');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_draft_has_no_published_date '
            ."CHECK (status <> 'draft' OR published_at IS NULL)"
        );

        // Une suspension sans motif n'est pas auditable.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_suspension_requires_reason');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_suspension_requires_reason '
            .'CHECK (suspended_at IS NULL OR suspension_reason IS NOT NULL)'
        );

        // Un slug vide ou mal formé ne doit pas entrer en base : la validation
        // applicative est la première barrière, celle-ci est la dernière.
        DB::statement('ALTER TABLE brands DROP CONSTRAINT IF EXISTS brands_slug_format');
        DB::statement(
            'ALTER TABLE brands ADD CONSTRAINT brands_slug_format '
            ."CHECK (slug ~ '^[a-z0-9]([a-z0-9-]{0,158}[a-z0-9])?$')"
        );
    }

    public function down(): void
    {
        // Ne détruit que ce que up() a créé (règle #8207).
        foreach ([
            'brands_slug_format',
            'brands_suspension_requires_reason',
            'brands_draft_has_no_published_date',
            'brands_published_requires_date',
            'brands_status_allowed',
            'brands_vertical_allowed',
        ] as $constraint) {
            DB::statement("ALTER TABLE brands DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        DB::statement('DROP INDEX IF EXISTS brands_host_unique');
        DB::statement('DROP INDEX IF EXISTS brands_one_default_per_company');

        Schema::dropIfExists('brands');
    }
};
