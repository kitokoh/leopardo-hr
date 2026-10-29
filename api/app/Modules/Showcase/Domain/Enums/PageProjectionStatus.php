<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Statut de Page attendu par la projection `company_showcases` -> `pages` (#8727).
 *
 * ## Pourquoi un enum LOCAL et non `App\Modules\Pages\Domain\Enums\PageStatus`
 *
 * La projection vit dans la couche **Domain** de Showcase. Importer l'enum de
 * Pages depuis cet endroit créerait une arête `Modules/Showcase -> Modules/Pages`
 * au coeur métier, interdite par la règle d'isolation (ARCHITECTURE.md §2,
 * garde #5584) : un BC ne connaît pas l'intérieur d'un autre BC.
 *
 * Le besoin réel n'est pas l'enum de Pages, c'est **deux valeurs** que Showcase
 * possède déjà conceptuellement (`draft`, `published`). On les déclare ici, et
 * c'est la **couche Infrastructure** (`ShowcasePagesBackfillCommand`, qui écrit
 * dans les tables `pages` et a donc le droit de connaître Pages) qui effectue la
 * correspondance vers `PageStatus`. La règle de décision reste ainsi testable
 * dans le Domain sans aucune dépendance.
 *
 * Les valeurs de chaîne sont **volontairement identiques** à celles de
 * `PageStatus` : la traduction en Infrastructure est un `match` exhaustif sur
 * ces valeurs, et le backfill écrit `->value` directement.
 */
enum PageProjectionStatus: string
{
    case Draft = 'draft';

    case Published = 'published';
}
