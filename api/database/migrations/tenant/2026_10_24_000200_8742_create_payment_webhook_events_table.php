<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-38 PAIEMENTS (#8742) — journal des evenements de webhook entrants.
 *
 * Constat d'audit : le webhook verifiait la signature et resolvait l'instance,
 * mais **n'ecrivait rien en base** — il appelait le rail et renvoyait son
 * statut. Aucun contrat n'existait pour un evenement hors sequence, en retard
 * ou duplique : un rejeu etait traite comme un evenement neuf, et un
 * `succeeded` recu apres un `succeeded` local pouvait faire regresser l'etat.
 *
 * Cette table porte les deux garanties qui manquaient :
 *
 * 1. **Idempotence** — `event_id` (identifiant fournisseur, ou empreinte
 *    deterministe du corps si le fournisseur n'en fournit pas) est unique par
 *    instance. Un rejeu est un **no-op** qui retourne le resultat existant.
 * 2. **Tracabilite du hors-sequence** — un evenement qui contredirait un etat
 *    terminal est **enregistre** avec son verdict (`out_of_sequence`) au lieu
 *    d'etre silencieusement ignore. Un webhook perdu se diagnostique alors en
 *    lisant la table, pas en fouillant les logs.
 *
 * `company_id` est nullable : un endpoint d'instance globale recoit des
 * evenements sans tenant (l'appelant est un fournisseur, pas un utilisateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();

            // NULL = evenement d'une instance globale (meme convention que
            // `payment_webhook_endpoints`).
            $table->uuid('company_id')->nullable()->index();

            $table->unsignedBigInteger('provider_instance_id');
            $table->unsignedBigInteger('webhook_endpoint_id')->nullable();

            // Identifiant fournisseur, ou empreinte deterministe du corps.
            $table->string('event_id', 190);

            // Reference externe de la transaction visee (peut etre vide si le
            // fournisseur notifie un evenement sans reference exploitable).
            $table->string('external_reference', 190)->nullable();

            $table->string('event_type', 60);

            // processed | replayed | out_of_sequence | no_transaction
            $table->string('outcome', 30);

            // Statut demande par l'evenement et statut effectivement applique :
            // les deux sont conserves pour rendre un ecart lisible.
            $table->string('requested_status', 20)->nullable();
            $table->string('applied_status', 20)->nullable();

            $table->unsignedBigInteger('transaction_id')->nullable();

            // Empreinte du corps recu : permet de detecter un meme `event_id`
            // rejoue avec un contenu DIFFERENT (signe d'une anomalie amont).
            $table->string('payload_hash', 64);

            $table->timestampTz('received_at');
            $table->timestampsTz();

            // Idempotence : un evenement fournisseur ne s'applique qu'une fois
            // par instance. C'est la BASE qui garantit l'unicite, pas un `if`.
            $table->unique(
                ['provider_instance_id', 'event_id'],
                'payment_webhook_events_instance_event_unique',
            );

            $table->index(['company_id', 'received_at']);
            $table->index('external_reference');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
