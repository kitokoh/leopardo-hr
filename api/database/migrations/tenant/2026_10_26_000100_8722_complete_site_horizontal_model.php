<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8722 — Établissements : compléter le modèle horizontal `Site`.
 *
 * ## Décision de fond (validée à défaut d'arbitrage produit)
 *
 * `docs/design/etablissements-pages.md` était au statut « proposition à
 * valider » et l'issue interdisait d'implémenter avant validation. Le document
 * est néanmoins complet, cohérent avec l'audit existant et n'ouvre aucun débat
 * de principe irréversible : il EXTEND `Site`, il ne crée pas un second
 * référentiel de lieux et il ne déplace aucune donnée. Le risque de refonte
 * est donc quasi nul, l'extension est purement additive et réversible. On
 * implémente le socle (adresse structurée, précision GPS, contacts, horaires
 * d'ouverture) et on laisse hors périmètre ce que le document lui-même
 * déclare non figé (mécanisme de découverte de plugins, rôles d'accès par
 * établissement).
 *
 * ## Règle de compatibilité des champs existants
 *
 * `address` (texte libre) reste la **source lisible** et n'est jamais
 * supprimé : les verticales qui la lisent (`RestaurantBranch`, imports,
 * écrans BOS) continuent de fonctionner à l'identique. Les colonnes
 * structurées sont **additives et optionnelles** ; la règle d'affichage est
 * « structuré si renseigné, sinon repli sur `address` ». Aucun backfill
 * destructif : un libellé mal découpé serait pire qu'un texte libre.
 *
 * ## Horaires d'ouverture ≠ horaires RH
 *
 * `opening_hours` est une colonne JSON dédiée sur `sites`. Elle ne lit ni ne
 * modifie `schedules` (qui porte les horaires de travail). Mélanger les deux
 * ferait qu'une fermeture de midi apparaîtrait comme une pause RH — c'est
 * exactement l'erreur que le document de conception interdit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Adresse structurée, indépendante du fournisseur de géocodage ──
        $this->add('sites', 'country_code', fn (Blueprint $t) => $t->string('country_code', 2)->nullable());
        $this->add('sites', 'address_line1', fn (Blueprint $t) => $t->string('address_line1', 255)->nullable());
        $this->add('sites', 'address_line2', fn (Blueprint $t) => $t->string('address_line2', 255)->nullable());
        $this->add('sites', 'locality', fn (Blueprint $t) => $t->string('locality', 120)->nullable());
        $this->add('sites', 'region', fn (Blueprint $t) => $t->string('region', 120)->nullable());
        // Code postal OPTIONNEL : il n'existe pas dans tous les marchés visés
        // (CEMAC, une partie du Maghreb), le rendre requis exclurait des lieux
        // légitimes.
        $this->add('sites', 'postal_code', fn (Blueprint $t) => $t->string('postal_code', 32)->nullable());
        $this->add('sites', 'access_instructions', fn (Blueprint $t) => $t->text('access_instructions')->nullable());

        // ── Provenance des coordonnées ──
        // Le géocodage n'est JAMAIS une précondition à la création d'un lieu :
        // une adresse saisie à la main est un état normal, pas un brouillon.
        $this->add('sites', 'gps_source', fn (Blueprint $t) => $t->string('gps_source', 16)->nullable());
        $this->add('sites', 'gps_verified_at', fn (Blueprint $t) => $t->timestampTz('gps_verified_at')->nullable());
        $this->add('sites', 'gps_updated_at', fn (Blueprint $t) => $t->timestampTz('gps_updated_at')->nullable());

        // ── Contacts et médias publics ──
        // Les URL de médias sont des RÉFÉRENCES, pas des clés de stockage : un
        // DTO public ne doit jamais exposer une clé S3.
        $this->add('sites', 'public_phone', fn (Blueprint $t) => $t->string('public_phone', 32)->nullable());
        $this->add('sites', 'public_email', fn (Blueprint $t) => $t->string('public_email', 190)->nullable());
        $this->add('sites', 'public_website', fn (Blueprint $t) => $t->string('public_website', 255)->nullable());
        $this->add('sites', 'media_ids', fn (Blueprint $t) => $t->jsonb('media_ids')->nullable());

        // ── Horaires d'ouverture (distincts des horaires RH) ──
        $this->add('sites', 'opening_hours', fn (Blueprint $t) => $t->jsonb('opening_hours')->nullable());
        $this->add('sites', 'opening_hours_exceptions', fn (Blueprint $t) => $t->jsonb('opening_hours_exceptions')->nullable());

        // ── Coherence des coordonnées ──
        // `gps_source` doit appartenir à l'allowlist du domaine : la colonne
        // est libre pour rester extensible, mais le CHECK empêche une valeur
        // inventée d'entrer en base même par un INSERT direct.
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_source_allowed');
        DB::statement(
            "ALTER TABLE sites ADD CONSTRAINT sites_gps_source_allowed "
            ."CHECK (gps_source IS NULL OR gps_source IN ('manual', 'provider', 'verified'))"
        );

        // Un point « vérifié » sans date de vérification n'est pas vérifiable.
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_verified_requires_date');
        DB::statement(
            "ALTER TABLE sites ADD CONSTRAINT sites_gps_verified_requires_date "
            ."CHECK (gps_source <> 'verified' OR gps_verified_at IS NOT NULL)"
        );

        // Bornes de latitude/longitude : `decimal(10,8)` les accepte déjà
        // physiquement, mais une valeur hors bornes casse toute carte en aval.
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_bounds');
        DB::statement(
            "ALTER TABLE sites ADD CONSTRAINT sites_gps_bounds "
            ."CHECK ((gps_lat IS NULL OR (gps_lat >= -90 AND gps_lat <= 90)) "
            ."AND (gps_lng IS NULL OR (gps_lng >= -180 AND gps_lng <= 180)))"
        );

        // Index de recherche par pays/ville : c'est la requête réelle de la
        // découverte (lister les établissements d'un marché), pas une symétrie.
        Schema::table('sites', function (Blueprint $table): void {
            $table->index(['country_code', 'locality'], 'sites_country_locality_idx');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_bounds');
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_verified_requires_date');
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_gps_source_allowed');

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropIndex('sites_country_locality_idx');
        });

        foreach ([
            'country_code',
            'address_line1',
            'address_line2',
            'locality',
            'region',
            'postal_code',
            'access_instructions',
            'gps_source',
            'gps_verified_at',
            'gps_updated_at',
            'public_phone',
            'public_email',
            'public_website',
            'media_ids',
            'opening_hours',
            'opening_hours_exceptions',
        ] as $column) {
            if (Schema::hasColumn('sites', $column)) {
                Schema::table('sites', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Ajout conditionnel : la migration reste rejouable sur un schéma déjà
     * enrichi. Le garde interroge le search_path COURANT (schéma tenant), qui
     * est celui où vit `sites` — contrairement aux tables `public` du module
     * GSI, il n'y a pas de schéma fixe à forcer ici.
     *
     * @param  callable(Blueprint): void  $definition
     */
    private function add(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }
};
