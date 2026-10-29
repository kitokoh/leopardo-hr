<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Repositories\RestaurantKitchenActionRepository;

/**
 * #8448 — applique un geste cuisine venu du KDS Edge, de façon IDEMPOTENTE.
 *
 * Le KDS hors ligne rejoue ses gestes au retour du réseau. Un rejeu est
 * inévitable (réponse perdue, retry, deux tablettes) et ne doit jamais faire
 * avancer une commande deux fois : un ticket « prêt » qui saute à « servi »
 * sans que personne ne l'ait servi est un ticket perdu en salle.
 *
 * ## Comment l'idempotence est garantie
 *
 * Par la **contrainte d'unicité** de `restaurant_kitchen_actions`
 * (`company_id` + `client_action_id`), pas par un `SELECT` préalable. Un
 * `SELECT` puis `INSERT` laisserait passer deux rejeux concurrents : les deux
 * liraient « absent » avant que l'un des deux n'écrive. Ici le second `INSERT`
 * échoue au niveau du moteur, et on renvoie l'état déjà appliqué.
 *
 * ## Pourquoi on ne lève pas sur un rejeu
 *
 * Un rejeu n'est pas une erreur : c'est le fonctionnement normal d'un client
 * hors ligne. Répondre 409 ferait afficher une erreur au cuisinier pour un
 * geste qui a en réalité réussi. On répond 200 avec l'état courant.
 */
final class ApplyKitchenActionAction
{
    public function __construct(
        private readonly TransitionOrderAction $transitionAction,
        private readonly RestaurantKitchenActionRepository $actions,
    ) {}

    /**
     * @return array{order: RestaurantOrder, replayed: bool}
     */
    public function execute(
        Employee $actor,
        RestaurantOrder $order,
        string $clientActionId,
        string $action,
    ): array {
        $target = match ($action) {
            'start' => OrderStatus::IN_PREPARATION,
            'ready' => OrderStatus::READY,
            'serve' => OrderStatus::SERVED,
            default => null,
        };

        if ($target === null) {
            abort(422, 'Unknown kitchen action.');
        }

        // Rejeu déjà enregistré : on renvoie l'état courant sans retransitionner.
        if ($this->actions->alreadyApplied((string) $actor->company_id, $clientActionId)) {
            return ['order' => $order->fresh() ?? $order, 'replayed' => true];
        }

        $updated = $this->transitionAction->execute($actor, $order, $target);

        // `record` retourne `false` si un rejeu concurrent a gagné la course :
        // la transition a bien été appliquée par cette requête, l'état est
        // correct, et on ne rejoue pas — c'est exactement ce que l'idempotence
        // doit produire.
        $this->actions->record(
            (string) $actor->company_id,
            $clientActionId,
            (int) $order->id,
            $action,
            $target->value,
        );

        return ['order' => $updated, 'replayed' => false];
    }
}
