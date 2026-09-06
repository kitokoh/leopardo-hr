<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Statut de vie de la vitrine publique d'un tenant (BC-27 SHOWCASE, #6865).
 *
 * - `draft`     : visible uniquement en gestion (tenant) + apercu prive,
 *                 jamais sur les routes publiques ;
 * - `published` : visible sur `GET /public/vitrine/{slug}` (US3/US6).
 *
 * Stocke en string en base (colonne `status`, defaut `draft`) ; l'enum PHP
 * est la source de verite cote code (pattern RestaurantManager, #6167).
 */
enum ShowcaseStatus: string
{
    case Draft = 'draft';

    case Published = 'published';
}
