<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3 (#9105) · Index PLATEFORME des activités du client public.
 *
 * Un client de la passerelle publique commande chez un restaurant, réserve une
 * table ailleurs, et ces lignes vivent dans des schémas tenants DISTINCTS
 * (isolation stricte). Pour afficher « mes activités », il faut donc savoir
 * QUELS schémas interroger avant d'ouvrir un contexte tenant — et il est hors
 * de question de parcourir `pg_namespace` depuis le code applicatif.
 *
 * Cette table ne contient AUCUN contenu métier : c'est une table de ROUTAGE
 * (client → tenant + référence), volontairement minimale :
 *
 *   - `user_id`      : le client (`users.id`, guard `user_api`) ;
 *   - `company_id`   : le tenant qui héberge l'activité ;
 *   - `vertical`     : la verticale d'origine (`restaurant`, `retail`, …) ;
 *   - `activity_type`: `order` | `booking` ;
 *   - `external_ref` : la référence DANS le schéma du tenant (`RST-…`, `RSV-…`) ;
 *   - `status`       : PROJECTION, jamais la vérité — voir ci-dessous ;
 *   - `occurred_at`  : date de l'activité, pour le tri.
 *
 * ⚠️ **`status` est une projection, pas une autorité.** Il est écrit au moment
 * de l'événement de la verticale pour permettre un tri et un affichage
 * immédiats ; il peut être en retard d'un événement. Chaque lecture rejoue le
 * contrôle d'accès ET relit le statut réel DANS le contexte tenant via le
 * `PublicActivityProvider` de la verticale. Un statut périmé ici ne peut donc
 * jamais faire croire qu'une commande est payée alors qu'elle ne l'est pas.
 *
 * ⚠️ **Cette table n'autorise RIEN.** Elle dit seulement où chercher. Un
 * `external_ref` présent ici ne suffit jamais à lire une commande : le provider
 * de la verticale revérifie l'appartenance au client dans son propre schéma.
 *
 * Même patron que `public_conversation_index` (#8760) : les données d'un
 * visiteur sans tenant vivent dans le schéma `public`, jamais dans un schéma
 * tenant.
 *
 * Idempotente (règle « une table, une migration ») : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.public_activity_index')) {
            return;
        }

        // Qualifié `public.` explicitement : le `search_path` applicatif est
        // `shared_tenants,public` (config/database.php:97). Sans qualification,
        // un `migrate --path` lancé hors du runner `leopardo:migrate` créerait
        // la table dans `shared_tenants`.
        Schema::create('public.public_activity_index', function (Blueprint $table): void {
            $table->id();

            // Client public (users.id) et tenant hôte de l'activité.
            $table->unsignedBigInteger('user_id');
            $table->uuid('company_id');

            // Verticale d'origine et nature de l'activité.
            $table->string('vertical', 40);
            $table->string('activity_type', 20);

            // Référence DANS le schéma du tenant — jamais un id interne.
            $table->string('external_ref', 80);

            // Projection seulement : la vérité est relue côté tenant.
            $table->string('status', 20);

            $table->timestampTz('occurred_at');

            $table->timestampsTz();

            // Idempotence de l'alimentation par événement : rejouer un événement
            // de verticale ne duplique pas la ligne.
            $table->unique(
                ['user_id', 'company_id', 'vertical', 'external_ref'],
                'public_activity_index_user_tenant_vertical_ref_unique',
            );

            // Lecture « mes activités », du plus récent au plus ancien.
            $table->index(['user_id', 'occurred_at'], 'public_activity_index_user_occurred_index');
            $table->index(['company_id', 'vertical', 'external_ref'], 'public_activity_index_company_ref_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.public_activity_index');
    }
};
