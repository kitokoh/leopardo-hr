<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing OS — socle de pilotage (stratégie, médiathèque, publications).
 *
 * Ajoute les QUATRE tables qui manquaient à la chaîne
 * « Idée → Stratégie → Brief → Production → Validation → Publication →
 * Mesure → Réutilisation » (audit `docs/specifications/MARKETING_OS_AUDIT.md`,
 * classe D). Le socle de publication existant (`social_accounts`,
 * `social_posts`, `post_publications`) n'est PAS re-déclaré : la garde
 * « une table, une migration » (#7452) et le comportement différé des
 * migrations (`if (! schemaTableExists())`) interdisent un second
 * `Schema::create` sur une table existante.
 *
 *   - `marketing_campaigns`     : campagne regroupant idées, ressources et
 *                                 publications, avec objectif, cible,
 *                                 verticale, message, budget et statut.
 *   - `content_ideas`           : idée / brief de production (format, canal,
 *                                 langue, dates, responsable, statut) et sa
 *                                 fiche studio (`brief`, JSON libre) ;
 *                                 `production_task_id` relie l'idée à la
 *                                 tâche de production SANS import croisé de
 *                                 module (#5584) — identifiant stable seul.
 *   - `marketing_assets`        : médiathèque — ressource versionnée et
 *                                 validable, avec métadonnées de recherche
 *                                 (campagne, objectif, produit, plateformes,
 *                                 langue, tags, droits d'utilisation).
 *                                 Les BINAIRES restent sur le disk hors
 *                                 webroot (`disk` + `path`) : jamais en base.
 *   - `marketing_publications`  : une ligne PAR plateforme cible, append-only
 *                                 — l'historique d'une publication n'est
 *                                 jamais écrasé par une nouvelle version.
 *                                 `mode` distingue explicitement la
 *                                 publication `direct` (API) de la
 *                                 publication `assisted` (manuelle).
 *
 * Conventions respectées : `company_id` uuid NON nullable + indexé sur
 * chaque table (isolation tenant, constitution §II) ; `timestampTz` partout ;
 * références internes par identifiant stable SANS clé étrangère
 * (conventions migrations tenant §2.6) ; `up()` idempotent et `down()` qui ne
 * détruit QUE ce que ce même fichier a créé (garde #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('marketing_campaigns')) {
            Schema::create('marketing_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 120);
                $table->text('description')->nullable();

                // notoriete | acquisition | conversion | fidelisation | lancement
                $table->string('objective', 30)->default('notoriete')->index();

                // Public visé et secteur / verticale Leopardo (restaurant,
                // transport, pharmacie, commerce, hôtellerie…).
                $table->text('audience')->nullable();
                $table->string('sector', 60)->nullable()->index();

                // Produit ou fonctionnalité Leopardo mise en avant.
                $table->string('product', 120)->nullable();
                $table->text('key_message')->nullable();
                // Canal de diffusion principal (organique, paid_social, email…).
                $table->string('channel', 60)->nullable();

                $table->string('locale', 10)->default('fr');

                $table->timestampTz('starts_at')->nullable();
                $table->timestampTz('ends_at')->nullable();

                $table->unsignedBigInteger('owner_id')->nullable();

                $table->decimal('budget_amount', 14, 2)->nullable();
                $table->string('budget_currency', 3)->default('EUR');

                // draft | active | paused | finished | cancelled
                $table->string('status', 20)->default('draft')->index();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'sector']);
            });
        }

        if (! schemaTableExists('content_ideas')) {
            Schema::create('content_ideas', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('campaign_id')->nullable()->index();

                $table->string('title', 180);
                $table->text('description')->nullable();

                // notoriete | acquisition | conversion | fidelisation | lancement
                $table->string('objective', 30)->nullable();

                // video_short | video_long | image | carousel | article |
                // ad | testimonial | other
                $table->string('format', 30)->default('other')->index();

                $table->string('channel', 60)->nullable();
                $table->string('locale', 10)->default('fr');

                $table->text('audience')->nullable();
                $table->string('product', 120)->nullable();
                $table->string('sector', 60)->nullable()->index();
                $table->text('key_message')->nullable();

                // Dates souhaitées : production puis publication.
                $table->timestampTz('production_due_at')->nullable();
                $table->timestampTz('publish_due_at')->nullable();

                $table->unsignedBigInteger('owner_id')->nullable();

                $table->decimal('budget_amount', 14, 2)->nullable();
                $table->string('budget_currency', 3)->default('EUR');

                // idea | briefing | in_production | in_review | approved |
                // scheduled | published | cancelled
                $table->string('status', 24)->default('idea')->index();

                $table->text('next_action')->nullable();

                // Fiche studio : concept, script, storyboard, scènes,
                // narration, indications de montage, prompt vidéo, prompts
                // image, ressources, format/durée, versions linguistiques.
                $table->json('brief')->nullable();

                // Pont vers la tâche de production (module Planning) : on ne
                // stocke que l'identifiant — aucun import croisé de module
                // n'est autorisé (#5584).
                $table->unsignedBigInteger('production_task_id')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'campaign_id']);
            });
        }

        if (! schemaTableExists('marketing_assets')) {
            Schema::create('marketing_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('campaign_id')->nullable()->index();
                $table->unsignedBigInteger('content_idea_id')->nullable()->index();

                $table->string('name', 200);
                $table->text('description')->nullable();

                // video | image | logo | illustration | script | prompt |
                // copy | document | audio | other
                $table->string('kind', 24)->default('other')->index();

                // Emplacement de stockage (disk hors webroot + chemin interne).
                // Les binaires ne sont JAMAIS stockés dans la base : ces deux
                // colonnes pointent vers le système de fichiers existant.
                $table->string('mime_type', 120)->nullable();
                $table->string('extension', 12)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->string('disk', 40)->nullable();
                $table->string('path', 512)->nullable();
                $table->string('preview_path', 512)->nullable();
                $table->string('thumbnail_path', 512)->nullable();

                $table->string('locale', 10)->default('fr')->index();
                $table->string('product', 120)->nullable()->index();
                $table->string('objective', 30)->nullable();
                $table->string('sector', 60)->nullable()->index();

                // Plateformes destinataires et tags personnalisés.
                $table->json('platforms')->nullable();
                $table->json('tags')->nullable();

                $table->unsignedInteger('version')->default(1);

                // draft | pending | approved | rejected
                $table->string('validation_status', 20)->default('draft')->index();

                $table->unsignedBigInteger('author_id')->nullable();

                // Droits d'utilisation et éventuelle date d'expiration.
                $table->text('rights')->nullable();
                $table->timestampTz('rights_expires_at')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'kind']);
                $table->index(['company_id', 'validation_status']);
                // Une version unique par ressource nommée : versionner ne
                // remplace jamais la ligne précédente.
                $table->unique(['company_id', 'name', 'version'], 'marketing_assets_name_version_unique');
            });
        }

        if (! schemaTableExists('marketing_publications')) {
            Schema::create('marketing_publications', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('content_idea_id')->nullable()->index();

                // Ressource utilisée (médiathèque) : une même ressource peut
                // alimenter PLUSIEURS publications — c'est ce lien qui répond
                // à « cette vidéo a-t-elle déjà été publiée, où, quand ? ».
                $table->unsignedBigInteger('marketing_asset_id')->nullable()->index();

                // Post du socle de publication existant (Modules\Marketing) :
                // lien par identifiant, jamais par import croisé.
                $table->unsignedBigInteger('social_post_id')->nullable()->index();

                $table->string('platform', 40)->index();

                // direct (API / agrégateur configuré) | assisted (manuel).
                // Le mode est TOUJOURS exposé : aucune publication assistée
                // n'est présentée comme automatisée.
                $table->string('mode', 16)->default('assisted');

                // idea | in_preparation | in_review | approved | scheduled |
                // published | failed | cancelled
                $table->string('status', 20)->default('idea')->index();

                $table->string('title', 200)->nullable();
                $table->text('body')->nullable();
                $table->text('hashtags')->nullable();
                $table->text('call_to_action')->nullable();
                $table->string('destination_url', 512)->nullable();

                $table->timestampTz('scheduled_at')->nullable()->index();
                $table->timestampTz('published_at')->nullable();

                // URL réelle et compte réellement utilisé : renseignés après
                // succès API OU confirmation explicite de l'utilisateur.
                $table->string('published_url', 512)->nullable();
                $table->string('platform_account', 120)->nullable();

                // Statistiques disponibles + provenance (api | manual |
                // unavailable). Aucune estimation n'est présentée comme un fait.
                $table->json('stats')->nullable();
                $table->string('stats_source', 16)->nullable();

                $table->text('error_message')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->json('attempt_history')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'platform', 'status']);
                $table->index(['company_id', 'marketing_asset_id']);
                $table->index(['company_id', 'status']);
            });
        }

        // Tâche de production marketing. Volontairement dans le module Marketing
        // et NON dans `tasks` (Planning) : `tasks.created_by` est une clé
        // étrangère OBLIGATOIRE vers `employees` (migration 2026_04_01_000104),
        // or l'acteur qui promeut une idée depuis le Super Admin est un compte
        // plateforme sans fiche employé. Créer une ligne Planning exigerait un
        // employé fictif ou l'assouplissement d'une contrainte du module
        // Planning : deux effets de bord inacceptables. La chaîne
        // Idée → Production reste tracée par `content_ideas.production_task_id`.
        if (! schemaTableExists('marketing_production_tasks')) {
            Schema::create('marketing_production_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('content_idea_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->date('due_date');
                $table->string('priority', 16)->default('normal');
                $table->string('category', 60)->default('marketing');
                $table->string('status', 20)->default('todo');
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE marketing_campaigns IS 'Campagnes marketing d''un tenant : objectif, cible, verticale, budget et statut (Marketing OS).';");
            DB::statement("COMMENT ON TABLE content_ideas IS 'Idees et briefs de production marketing : format, canal, langue, dates, fiche studio JSON (Marketing OS).';");
            DB::statement("COMMENT ON TABLE marketing_assets IS 'Mediatheque marketing : ressources versionnees et validables, binaires hors base (Marketing OS).';");
            DB::statement("COMMENT ON TABLE marketing_publications IS 'Publications par plateforme, append-only, mode direct ou assiste, URL et date reelles (Marketing OS).';");
            DB::statement("COMMENT ON TABLE marketing_production_tasks IS 'Taches de production marketing issues de la promotion d''une idee de contenu (Marketing OS).';");
        }
    }

    public function down(): void
    {
        // Ce `down()` ne détruit QUE les tables créées par ce même fichier
        // (garde #8207 — un `down()` tierce ne doit jamais dropper l'existant).
        Schema::dropIfExists('marketing_production_tasks');
        Schema::dropIfExists('marketing_publications');
        Schema::dropIfExists('marketing_assets');
        Schema::dropIfExists('content_ideas');
        Schema::dropIfExists('marketing_campaigns');
    }
};
