<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut de vie d'un produit du module Retail (BC-17 RETAIL, #7672).
 *
 * - `draft`    : visible uniquement côté gestion (tenant), jamais vendable ;
 * - `published`: produit actif, vendable en caisse/boutique ;
 * - `archived` : produit retiré du catalogue (conservé pour l'historique).
 *
 * Le statut est stocké en string en base (colonne `status`, défaut `draft`) ;
 * l'enum PHP est la source de vérité côté code (pattern Catalog #6880).
 */
enum RetailProductStatus: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';
}
