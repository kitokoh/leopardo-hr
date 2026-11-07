<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

/**
 * COULOIR 16 — M3-bis (#9142) · Contrat partagé de notification du CLIENT public.
 *
 * ## Pourquoi ce contrat existe
 *
 * Une verticale (BC-25 RESTAURANT, BC-24 TRAVEL, …) sait qu'une commande a
 * changé d'état. Le client public, lui, doit être prévenu — c'est la
 * « première habitude quotidienne » du couloir 16. Mais la boîte du client vit
 * dans `public_notifications` (schéma `public`), propriété du module Platform
 * (BC-01), et **une verticale n'a pas le droit d'importer Platform** : la
 * garde d'isolation #5584 bloque tout nouvel import croisé, et l'arête
 * `BC-25 → BC-01` n'existe pas dans `bounded-context-dependencies.json`.
 *
 * Ce contrat est le seam : la verticale ne dépend que de cette interface,
 * implémentée par `Platform\Application\Services\PublicNotificationService`.
 * Même inversion de dépendance que `EmployeeNotifier` (BC-13) et
 * `PublicActivityProvider` (M3, #9105) — la verticale ne connaît jamais la
 * classe concrète.
 *
 * ## Best-effort
 *
 * L'implémentation ne lève jamais : une notification manquée ne doit pas faire
 * échouer la transition d'état, qui est la donnée métier. L'appelant peut donc
 * l'invoquer sans try/catch, mais le contrat ne l'exige pas.
 *
 * ## Aucune donnée personnelle
 *
 * `$merchantName` est le nom COMMERCIAL du commerce, jamais le nom d'un
 * employé. `$externalRef` est la référence publique de la commande. Aucun
 * numéro, aucune adresse, aucun prix ne traverse ce contrat.
 */
interface PublicClientNotifier
{
    /**
     * Prévient le client qu'une de ses activités a changé d'état.
     *
     * @param  int  $clientId  `users.id` du client public (guard `user_api`)
     * @param  string  $merchantName  nom commercial du commerce (donnée publique)
     * @param  string  $vertical  verticale d'origine (`restaurant`, `retail`, …)
     * @param  string  $activityType  `order` | `booking`
     * @param  string  $externalRef  référence publique dans le schéma du tenant
     * @param  string  $status  statut RELU côté tenant (jamais la projection de l'index)
     * @return bool `true` si la notification a été écrite, `false` sinon (best-effort)
     */
    public function notifyActivityStatus(
        int $clientId,
        string $merchantName,
        string $vertical,
        string $activityType,
        string $externalRef,
        string $status,
    ): bool;
}
