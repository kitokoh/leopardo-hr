<?php

declare(strict_types=1);

namespace App\Core\Outbox\Contracts;

use DateTimeInterface;

/**
 * #8209 — Contrat de publication d'evenements metier (outbox transactionnel).
 *
 * Le depot portait SIX implementations de l'outbox, chacune reimplementant la
 * meme mecanique avec ses propres variantes de bugs : `CRM`, `EduManager`,
 * `FuelStation`, `Platform`, `RestaurantManager`, `TravelAgency`. Le cout
 * n'etait pas la duplication de code mais la **divergence des garanties** :
 * certaines versions perdaient l'evenement quand `search_path` n'exposait pas
 * la table, d'autres avalaient une violation de contrainte en silence.
 *
 * Ce contrat est la facade unique : les modules publient, le core garantit.
 * Il ne fige pas le nom de la table — chaque module conserve la sienne, ce qui
 * permet une migration verticale par verticale sans fenetre de rupture.
 */
interface OutboxPublisher
{
    /**
     * Persiste un evenement dans l'outbox du module appelant.
     *
     * Garanties (contrat du core, pas du module) :
     *  - l'evenement est ecrit dans la transaction de l'appelant (atomicite) ;
     *  - une erreur d'ecriture ne laisse jamais la transaction appelante
     *    avortee (savepoint) ;
     *  - deux publications identiques ne creent qu'une ligne (idempotence).
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string  $modelClass  Modele Eloquent de l'outbox cible (le core
     *                                    lit sa table via `getTable()` : il ne
     *                                    connait aucun modele de module).
     *
     * @throws \Throwable Remontee brute : c'est a l'appelant metier de decider
     *                    s'il absorbe (effet de bord auxiliaire) ou non.
     */
    public function publish(
        string $modelClass,
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): object;
}
