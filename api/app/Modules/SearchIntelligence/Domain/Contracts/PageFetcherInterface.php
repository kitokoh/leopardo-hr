<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Contracts;

/**
 * GSI-2 (#8513) — récupération du HTML d'une page du parc.
 *
 * Le scanner ne parle pas directement à `Http::get()` : il dépend de ce
 * contrat. Deux raisons, toutes les deux apprises à la dure :
 *
 *  - **testabilité réelle** — les tests de `SeoScanTest` doivent servir un
 *    serveur stub local sans que la suite ait besoin d'accès réseau ;
 *  - **lecture seule garantie** — l'implémentation HTTP n'expose que `GET`.
 *    Aucune méthode d'écriture n'existe dans ce contrat, donc aucun chemin de
 *    code ne peut modifier la production depuis le scanner (critère
 *    d'acceptation « le scanner ne fait que lire »).
 */
interface PageFetcherInterface
{
    /**
     * Récupère une page.
     *
     * @return array{status: int, body: string}|null `null` si la requête n'a
     *         pas abouti (DNS, timeout, TLS…) — distinct d'un statut HTTP
     *         d'erreur, qui reste une réponse observée.
     */
    public function fetch(string $url): ?array;
}
