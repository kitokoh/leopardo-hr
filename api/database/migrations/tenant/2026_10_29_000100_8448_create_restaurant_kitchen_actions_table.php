<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8448 — journal d'idempotence des gestes cuisine hors ligne.
 *
 * ## Pourquoi cette table existe
 *
 * Le KDS Edge (#8448) met ses gestes en file locale quand le réseau tombe, puis
 * les rejoue au retour. Un rejeu est inévitable : si la requête part, que le
 * réseau coupe avant la réponse, et que le client réessaie, le serveur reçoit
 * le MÊME geste deux fois. Sans journal, la seconde exécution ferait avancer la
 * commande deux fois — un ticket « prêt » qui saute à « servi » sans que
 * personne ne l'ait servi.
 *
 * ## Pourquoi une clé primaire sur `client_action_id`
 *
 * L'idempotence doit être garantie par le MOTEUR, pas par un `SELECT` préalable
 * dans le code : deux rejeux concurrents (deux tablettes cuisine, ou un retry
 * qui double une requête lente) passeraient tous les deux le `SELECT` avant que
 * l'un des deux n'écrive. La contrainte d'unicité fait échouer le second
 * `INSERT` — c'est la seule garantie qui tient sous concurrence.
 *
 * ## Pourquoi `company_id` dans la clé
 *
 * `client_action_id` est généré par le client. Un client malveillant pourrait
 * réutiliser l'id d'un autre tenant pour faire sauter un geste légitime. La
 * portée tenant est donc portée par la table elle-même, et la lecture filtre
 * dessus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_kitchen_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('client_action_id', 64);
            $table->unsignedBigInteger('restaurant_order_id')->index();
            $table->string('action', 16);
            $table->string('resulting_status', 32);
            $table->timestamps();

            // La garantie d'idempotence : un même geste client ne peut être
            // enregistré qu'une fois par tenant.
            $table->unique(['company_id', 'client_action_id'], 'restaurant_kitchen_actions_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_kitchen_actions');
    }
};
