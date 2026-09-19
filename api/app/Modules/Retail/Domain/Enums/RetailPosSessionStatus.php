<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut d'une session de caisse POS Retail (BC-17 RETAIL, #7674).
 *
 * - `open`   : session ouverte, encaissements possibles ;
 * - `closed` : session cloturee (comptage, ecart), immuable.
 *
 * Une seule session `open` par (tenant, emplacement) — index unique partiel
 * Postgres (deviation du pattern restaurant #6173, cf. migration #7674).
 */
enum RetailPosSessionStatus: string
{
    case Open = 'open';

    case Closed = 'closed';
}
