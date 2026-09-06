<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

/**
 * Statut de vie d'un produit du catalogue B2B (BC-28 CATALOG, #6880).
 *
 * - `draft`    : visible uniquement côté gestion (tenant), jamais en public ;
 * - `published`: visible sur les routes publiques du catalogue.
 *
 * Le statut est stocké en string en base (colonne `status`, défaut `draft`) ;
 * l'enum PHP est la source de vérité côté code (pattern RestaurantManager,
 * #6167).
 */
enum CatalogProductStatus: string
{
    case Draft = 'draft';

    case Published = 'published';
}
