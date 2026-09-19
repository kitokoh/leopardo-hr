<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Moyen de paiement d'une commande Retail (BC-17 RETAIL, #7674).
 *
 * - `cash`   : especes (compte dans l'attendu de cloture de session) ;
 * - `card`   : carte bancaire (TPE externe, aucun flux integre) ;
 * - `mobile` : mobile money (Wave, Orange Money... — aucun flux integre) ;
 * - `online` : RESERVE a la future boutique e-commerce — valeur acceptee,
 *              AUCUNE integration passerelle en v1 (#7674).
 */
enum RetailPaymentMethod: string
{
    case Cash = 'cash';

    case Card = 'card';

    case Mobile = 'mobile';

    case Online = 'online';
}
