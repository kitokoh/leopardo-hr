<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M3 (#9105) · Historique de recherche du client public.
 *
 * ## Local d'abord, synchronisé si compte
 *
 * L'historique de recherche est d'abord une donnée de l'APPAREIL : il permet
 * « reprendre où vous étiez » et alimente le cache hors ligne. Il n'exige donc
 * aucun compte — c'est la condition du parcours « ouverture → recherche » sans
 * inscription.
 *
 * Cette table n'existe que pour le cas où la personne EST connectée : elle
 * permet de retrouver ses dernières recherches sur un autre appareil. Un
 * visiteur sans compte n'écrit jamais ici, et l'app continue de fonctionner
 * avec son historique local.
 *
 * ## Ce qui n'est PAS stocké
 *
 * Aucune position précise. `city` est la ville choisie ou déduite
 * APPROXIMATIVEMENT ; aucune latitude/longitude n'est conservée. La règle de
 * confidentialité du couloir est explicite : « position approximative par
 * défaut, précise sur demande, jamais conservée sans action ». Un historique de
 * recherche qui mémoriserait des coordonnées reconstituerait un trajet de vie
 * sans que la personne l'ait demandé.
 *
 * Idempotente : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.public_search_history')) {
            return;
        }

        Schema::create('public.public_search_history', function (Blueprint $table): void {
            $table->id();

            // Client public (users.id, guard `user_api`).
            $table->unsignedBigInteger('user_id');

            // Terme recherché, tel que saisi (tronqué : un historique n'est pas
            // un journal de frappe).
            $table->string('query', 160);

            // Ville choisie ou approximative. JAMAIS de coordonnées.
            $table->string('city', 120)->nullable();

            // Nombre de résultats obtenus : permet de distinguer « recherche
            // fructueuse » de « recherche sans résultat » dans l'écran
            // « reprendre où vous étiez », sans rejouer la requête.
            $table->unsignedInteger('result_count')->default(0);

            $table->timestampTz('searched_at');

            $table->timestampsTz();

            // Une même recherche répétée ne crée pas N lignes : elle remonte.
            $table->unique(
                ['user_id', 'query', 'city'],
                'public_search_history_user_query_city_unique',
            );

            // Lecture « mes dernières recherches ».
            $table->index(['user_id', 'searched_at'], 'public_search_history_user_searched_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.public_search_history');
    }
};
