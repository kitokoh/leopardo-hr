<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7967 — le plan free Render endort le conteneur web après ~15 min
 * d'inactivité, et le scheduler tourne DEDANS (`WEB_SCHEDULER_LOOP`,
 * docker-entrypoint.sh). Toute tâche due pendant le spin-down est PERDUE :
 * `schedule:run` ne rattrape rien, Laravel ne relit pas l'historique.
 *
 * Conséquence réelle : acquisition de congés, relances billing/contrats,
 * génération de factures et réconciliation de paiements pouvaient sauter
 * silencieusement une journée entière sans que personne ne le remarque.
 *
 * Cette table est le registre des passages du scheduler : une ligne par
 * (commande, échéance théorique). Elle sert deux fois :
 *   1. `scheduler:catch-up` compare les échéances théoriques (expression cron
 *      de la commande) aux lignes réellement présentes pour repérer les
 *      créneaux manqués ;
 *   2. elle rend le rattrapage IDEMPOTENT : un créneau déjà rejoué (ou déjà
 *      exécuté normalement) porte une ligne, donc il ne peut pas être rejoué
 *      deux fois — y compris si le conteneur redémarre en boucle.
 *
 * `key` est dérivé de la commande + son expression cron : deux entrées de
 * planning distinctes portant la même commande (fréquences différentes) ne
 * se confondent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduler_slot_runs')) {
            return;
        }

        Schema::create('scheduler_slot_runs', function (Blueprint $table): void {
            $table->id();

            // Identifiant stable de l'entrée de planning (sha1 commande+cron).
            $table->string('key', 64);

            // Le nom de commande tel qu'il apparaît dans `schedule:list`
            // (« billing:generate-invoices », arguments compris).
            $table->string('command', 255);

            $table->string('expression', 100);

            // Échéance THÉORIQUE du créneau (alignée sur l'expression cron,
            // pas sur l'instant réel du run) : c'est la clé du dédoublonnage.
            $table->timestamp('due_at');

            // 'scheduled' = exécution normale par schedule:run ;
            // 'catch_up'  = rejouée après un spin-down par scheduler:catch-up.
            $table->string('trigger', 20)->default('scheduled');

            $table->timestamp('executed_at');
            $table->timestamps();

            // Un créneau donné ne peut être enregistré qu'une fois : c'est
            // cette contrainte — et non la logique applicative — qui garantit
            // qu'un rattrapage ne double jamais une facture.
            $table->unique(['key', 'due_at'], 'scheduler_slot_runs_key_due_at_unique');

            // Balayage « qu'est-ce qui a tourné récemment ? » et purge.
            $table->index(['due_at'], 'scheduler_slot_runs_due_at_index');
            $table->index(['command', 'due_at'], 'scheduler_slot_runs_command_due_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_slot_runs');
    }
};
