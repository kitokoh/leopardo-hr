<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8723 (BC PAGES) — agrégat public `Page` rattaché à un établissement.
 *
 * ## Pourquoi une table neuve plutôt qu'une extension de `company_showcases`
 *
 * La vitrine existante (BC-27 SHOWCASE) est **une par tenant** : sa clé
 * d'unicité est `company_id`. Une Page appartient à un **Établissement**
 * (`sites`), plusieurs par tenant. Étendre la table existante imposerait de
 * casser sa contrainte d'unicité en production, sur des données vivantes, pour
 * un modèle dont les états diffèrent (`verified`/`suspended` n'existent pas
 * côté Showcase). On ajoute donc une table additive : la migration destructive
 * est explicitement le DERNIER lot (#8727), jamais le premier.
 *
 * ## Invariants portés par le schéma (pas seulement par le code)
 *
 * - **une seule Page par établissement en V1** — index UNIQUE sur `site_id`
 *   (décision produit documentée dans `docs/design/etablissements-pages.md`
 *   §11.1 : « une seule Page par Site en V1 ») ;
 * - **slug globalement unique** — unique sur `slug`, indépendant de
 *   `company_showcases.slug` ;
 * - **publication ≠ vérification** — deux axes indépendants, deux colonnes :
 *   `status` (`draft|published`) et `verification_status`
 *   (`unverified|pending|verified|rejected|disputed|revoked`) ;
 * - **suspension** — `suspended_at` + `suspension_reason` : l'état suspendu
 *   est un état de publication effectif mais la trace de la décision est
 *   conservée pour la modération ;
 * - **isolation tenant** — `company_id` reste porté par la ligne (scope global
 *   `BelongsToCompany`) ET le domaine vérifie que le `site_id` appartient au
 *   même tenant.
 *
 * ## Compatibilité
 *
 * Additive et idempotente. `down()` ne détruit que ce que `up()` a créé
 * (règle AGENTS.md #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pages')) {
            Schema::create('pages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                // L'établissement porteur. Pas de FK (convention migrations
                // tenant §2.6) : l'isolation est portée par `company_id` et
                // vérifiée par le domaine.
                $table->unsignedBigInteger('site_id');

                // Slug PUBLIC, globalement unique sur toute la superapp.
                $table->string('slug', 160);

                // Axe 1 : publication.
                $table->string('status', 20)->default('draft');
                $table->timestampTz('published_at')->nullable();

                // Axe 2 : vérification de propriété du lieu (indépendant).
                $table->string('verification_status', 20)->default('unverified');
                $table->timestampTz('verified_at')->nullable();
                $table->string('verification_method', 20)->nullable();

                // Suspension / modération.
                $table->timestampTz('suspended_at')->nullable();
                $table->string('suspension_reason', 40)->nullable();

                // Contenu & rendu.
                $table->string('theme', 60)->default('default');
                $table->jsonb('settings')->nullable();
                $table->jsonb('legal')->nullable();

                // SEO éditable ; dérivé à défaut (règle documentée #8731).
                $table->jsonb('seo')->nullable();

                // Locales disponibles + langue par défaut (RTL supporté).
                $table->jsonb('locales')->nullable();
                $table->string('default_locale', 8)->default('fr');

                // Domaines personnalisés (premium) — le flux DNS/SSL est #8726 ;
                // la colonne existe pour que le canonical soit calculable.
                $table->string('custom_domain', 190)->nullable();

                // Version du contrat de blocs consommé (#8724).
                $table->unsignedSmallInteger('schema_version')->default(1);

                $table->timestamps();

                // Une Page par établissement (V1) — contrainte de base.
                $table->unique('site_id', 'pages_site_unique');

                // Slug globalement unique.
                $table->unique('slug', 'pages_slug_unique');

                $table->index('company_id', 'pages_company_index');
                $table->index(['status', 'verification_status'], 'pages_publication_verification_index');
                $table->index('custom_domain', 'pages_custom_domain_index');
            });

            DB::statement(
                "COMMENT ON TABLE pages IS 'Presence publique d un etablissement (BC PAGES, #8723) - une par site en V1, slug globalement unique, publication et verification independantes.'"
            );
        }

        // Allowlists d'états : une valeur inventée ne doit pas entrer en base
        // même par INSERT direct (même doctrine que #8722).
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_status_allowed');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_status_allowed '
            ."CHECK (status IN ('draft', 'published'))"
        );

        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_verification_status_allowed');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_verification_status_allowed '
            ."CHECK (verification_status IN ('unverified', 'pending', 'verified', 'rejected', 'disputed', 'revoked'))"
        );

        // Une Page publiée a forcément une date de publication.
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_published_requires_date');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_published_requires_date '
            ."CHECK (status <> 'published' OR published_at IS NOT NULL)"
        );

        // Symétriquement : une date de publication sur un brouillon signale un
        // bug d'écriture.
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_draft_has_no_published_date');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_draft_has_no_published_date '
            ."CHECK (status <> 'draft' OR published_at IS NULL)"
        );

        // « vérifiée » sans date ni méthode n'est pas vérifiable (#8024).
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_verified_requires_proof');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_verified_requires_proof '
            ."CHECK (verification_status <> 'verified' OR (verified_at IS NOT NULL AND verification_method IS NOT NULL))"
        );

        // Une suspension sans motif n'est pas auditable.
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_suspension_requires_reason');
        DB::statement(
            'ALTER TABLE pages ADD CONSTRAINT pages_suspension_requires_reason '
            .'CHECK (suspended_at IS NULL OR suspension_reason IS NOT NULL)'
        );
    }

    public function down(): void
    {
        // Ne détruit que ce que up() a créé (règle #8207).
        foreach ([
            'pages_suspension_requires_reason',
            'pages_verified_requires_proof',
            'pages_draft_has_no_published_date',
            'pages_published_requires_date',
            'pages_verification_status_allowed',
            'pages_status_allowed',
        ] as $constraint) {
            DB::statement("ALTER TABLE pages DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        Schema::dropIfExists('pages');
    }
};
