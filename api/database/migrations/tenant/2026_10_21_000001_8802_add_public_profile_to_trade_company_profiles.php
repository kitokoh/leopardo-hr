<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-01 (#8802) — BC-36 TRADE : profil public de l'entreprise commerciale.
 *
 * **Pourquoi cette migration existe.** L'audit Phase 1 a constaté que la
 * publication transverse était le principal obstacle au parcours public : sur
 * les 37 verticales, seules 5 étaient découvrables, et **aucune** verticale
 * sans opt-in n'en avait la moindre amorce. Trade en était le cas d'école :
 * `trade_company_profiles` porte l'identité commerciale (directions, marchés,
 * catégories produits) mais **aucune notion de publication** — un importateur
 * ou un grossiste ne pouvait pas être trouvé par un visiteur.
 *
 * **Sémantique d'opt-in retenue (la décision qui manquait).** Cette migration
 * définit la sémantique par défaut pour toute verticale qui n'en a pas :
 *
 *  1. **Opt-in explicite** — `is_public` vaut `false` par défaut. Rien n'est
 *     publié sans une action délibérée du professionnel. Aucune heuristique du
 *     type « si le profil est complet, publier » : ce serait une publication
 *     sans consentement, et une fuite de données commerciales.
 *  2. **Fail-closed à la lecture** — l'absence de ligne, un `is_public = false`,
 *     ou un `public_slug` nul excluent le professionnel des résultats. Un
 *     provider qui ne sait pas répondre n'expose rien.
 *  3. **Slug public global et unique** — `public_slug` est unique
 *     **cross-tenant** (index unique), comme pour Restaurant (#7746) et Retail :
 *     une URL publique ne doit jamais dépendre du tenant courant, sinon deux
 *     tenants pourraient revendiquer la même fiche.
 *  4. **Périmètre public minimal** — nom commercial, ville/pays, description
 *     éditoriale, catégories produits **choisies explicitement pour la
 *     publication**. Jamais de `company_id`, jamais de `notes` internes,
 *     jamais de `capabilities`.
 *
 * ⚠️ **Pourquoi Trade et pas Pharmacy.** Pharmacy est délibérément exclue de
 * ce lot : publier une officine expose des données de santé et relève de la
 * publicité pharmaceutique, dont le régime varie par pays (interdiction de la
 * publicité grand public pour les médicaments sur ordonnance en France, règles
 * différentes en Turquie et au Maghreb). Ce n'est pas une décision
 * d'ingénierie : elle est tracée dans l'issue dédiée. Trade (import/export,
 * B2B, grossiste) ne porte aucun contenu réglementé de cette nature, d'où le
 * choix de cette verticale pour matérialiser la sémantique.
 *
 * Additive et réentrante (garde `schemaTableExists` + `hasColumn`, règle
 * #5431) : elle ne recrée jamais la table (#7452) et `down()` est complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_company_profiles')) {
            return;
        }

        Schema::table('trade_company_profiles', function (Blueprint $table): void {
            $isNew = ! Schema::hasColumn('trade_company_profiles', 'is_public');

            if ($isNew) {
                // Opt-in explicite : DÉFAUT PRIVÉ. Aucune valeur par défaut à
                // `true` ne doit jamais apparaître ici.
                $table->boolean('is_public')->default(false);
            }

            if (! Schema::hasColumn('trade_company_profiles', 'public_slug')) {
                $table->string('public_slug', 160)->nullable()
                    ->unique('trade_company_profiles_public_slug_unique');
            }

            if (! Schema::hasColumn('trade_company_profiles', 'public_name')) {
                $table->string('public_name', 160)->nullable();
            }

            if (! Schema::hasColumn('trade_company_profiles', 'public_description')) {
                $table->text('public_description')->nullable();
            }

            if (! Schema::hasColumn('trade_company_profiles', 'public_city')) {
                $table->string('public_city', 120)->nullable();
            }

            if (! Schema::hasColumn('trade_company_profiles', 'public_published_at')) {
                $table->timestamp('public_published_at')->nullable();
            }

            if ($isNew) {
                // Index de l'annuaire public : filtre d'abord sur l'opt-in, puis
                // tri alphabétique sur le nom public. Le `public_slug` a déjà son
                // index unique.
                $table->index(
                    ['is_public', 'public_name'],
                    'trade_company_profiles_public_listing_idx'
                );
            }
        });

        DB::statement("COMMENT ON COLUMN trade_company_profiles.is_public IS 'Opt-in annuaire public - P2-01/#8802, defaut PRIVE (fail-closed).';");
        DB::statement("COMMENT ON COLUMN trade_company_profiles.public_slug IS 'Slug public unique GLOBAL (cross-tenant) - P2-01/#8802.';");
        DB::statement("COMMENT ON COLUMN trade_company_profiles.public_name IS 'Nom commercial publiable, distinct de la raison sociale - P2-01/#8802.';");
        DB::statement("COMMENT ON COLUMN trade_company_profiles.public_city IS 'Ville publiee volontairement (pas une adresse) - P2-01/#8802.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('trade_company_profiles')) {
            return;
        }

        Schema::table('trade_company_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('trade_company_profiles', 'is_public')) {
                $table->dropIndex('trade_company_profiles_public_listing_idx');
            }

            if (Schema::hasColumn('trade_company_profiles', 'public_slug')) {
                $table->dropUnique('trade_company_profiles_public_slug_unique');
            }

            $table->dropColumn([
                'is_public',
                'public_slug',
                'public_name',
                'public_description',
                'public_city',
                'public_published_at',
            ]);
        });
    }
};
