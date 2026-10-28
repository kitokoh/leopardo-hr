<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Outbox\Contracts\OutboxPublisher;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Str;

/**
 * Publication d'evenements FuelStation dans l'outbox (FUEL-015/019) / #8209.
 *
 * Persistance au moment de l'evenement metier, consommation asynchrone
 * idempotente par `fuel:outbox-dispatch`.
 *
 * #8209 — la mecanique vit desormais dans `DatabaseOutboxPublisher`. Deux
 * defauts de cette verticale sont corriges par construction :
 *  - l'INSERT n'etait PAS protege par un savepoint : une erreur SQL (colonne
 *    manquante, contrainte) avortait la transaction de l'appelant (25P02) ;
 *  - la cle d'idempotence etait suffixee d'un UUID quand `aggregateId` etait
 *    absent, donc CHAQUE publication produisait une cle differente : le
 *    dedoublonnage annonce dans la doc de la classe ne pouvait pas
 *    fonctionner sur ce chemin. La cle derive du contenu (event_type +
 *    aggregates + payload) est desormais stable.
 *
 * Comportement metier conserve : `_event_type` reste embarque dans le payload
 * (des consommateurs le lisent) et `available_at` est pose explicitement —
 * un evenement est du des sa seconde d'insertion.
 */
final class FuelOutboxPublisher
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?string $idempotencyKey = null,
    ): FuelOutboxEvent {
        /** @var FuelOutboxEvent $event */
        $event = $this->outbox->publish(
            FuelOutboxEvent::class,
            $companyId,
            $eventType,
            array_merge($payload, ['_event_type' => $eventType]),
            $idempotencyKey ?? $this->makeKey($eventType, $aggregateType, $aggregateId, $payload),
            $aggregateType,
            $aggregateId,
            now(),
        );

        return $event;
    }

    /**
     * Cle stable derivee du fait metier (jamais d'alea) : un rejeu du meme
     * evenement doit produire la meme cle, sinon le dedoublonnage est nul.
     *
     * @param  array<string, mixed>  $payload
     */
    private function makeKey(
        string $eventType,
        ?string $aggregateType,
        ?string $aggregateId,
        array $payload,
    ): string {
        // Sans aggregateId, on se rabat sur le CONTENU — un UUID aleatoire
        // rendait la cle unique a chaque appel (doublons garantis).
        $identity = $aggregateId ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return $eventType.'-'.($aggregateType ?? 'none').'-'.$identity;
    }
}
