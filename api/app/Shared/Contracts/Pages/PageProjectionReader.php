<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Pages;

/**
 * Contrat partagé de lecture de la projection Page d'un ÉTABLISSEMENT
 * (BC PAGES), #8727.
 *
 * ## Pourquoi ce contrat existe
 *
 * La double lecture `company_showcases` ↔ `pages` doit se faire **depuis
 * Showcase**, puisque c'est là que la requête publique est servie. Or importer
 * `Modules\Pages` depuis `Modules\Showcase` est une arête inter-BC interdite
 * par la règle d'isolation (`ARCHITECTURE.md` § 2, garde #5584) — la
 * vérification est faite en CI et l'allowlist n'est pas un raccourci
 * acceptable.
 *
 * Le module Showcase ne dépend donc que de ce contrat, implémenté côté Pages
 * (`Pages\Infrastructure\Services\PageProjectionReader`) et bindé par
 * `PagesServiceProvider`. C'est le pattern déjà utilisé pour
 * `Shared\Contracts\Catalog\PublishedProductsProvider`.
 *
 * ## Ce qui transite (et ce qui ne transite pas)
 *
 * Uniquement ce que la comparaison utilise : le statut, le slug et le nombre
 * de blocs. Pas d'identifiants, pas de contenu, pas de `company_id` — le
 * consommateur n'en a pas besoin, et une surface publique ne doit pas
 * collectionner de données internes « au cas où ».
 */
interface PageProjectionReader
{
    /**
     * Lecture de la Page portée par un établissement.
     *
     * @return array{status: string, slug: string, sections: int}|null
     *                                                                 `null` si l'établissement n'a pas encore de Page projetée —
     *                                                                 cas NORMAL avant backfill, et non une divergence.
     */
    public function forSite(int $siteId): ?array;
}
