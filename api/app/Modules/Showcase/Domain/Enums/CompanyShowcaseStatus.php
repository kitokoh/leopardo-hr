<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Statut de vie de la vitrine d'un tenant (BC-27 SHOWCASE, #6865).
 *
 * - `draft`    : visible uniquement côté gestion (tenant), jamais en public ;
 * - `published`: visible sur la route publique `/vitrine/{slug}`.
 *
 * Le statut est stocké en string en base (colonne `status`, défaut `draft`) ;
 * l'enum PHP est la source de vérité côté code (pattern RestaurantManager
 * #6167 / Catalog BC-28 #6880).
 */
enum CompanyShowcaseStatus: string
{
    case Draft = 'draft';

    case Published = 'published';
}
