<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Moyen de paiement d'une commande Retail (BC-17 RETAIL, #7674).
 *
 * - `cash`   : especes (compte dans l'attendu de cloture de session) ;
 * - `card`   : carte bancaire (TPE externe, aucun flux integre) ;
 * - `mobile` : mobile money (Wave, Orange Money... — aucun flux integre) ;
 * - `online` : paiement en ligne de la marketplace Leopardo Marche
 *              (#7812) — intent de paiement + provider PSP (chargily|mock),
 *              webhook signe et reconciliation via RetailPaymentService.
 */
enum RetailPaymentMethod: string
{
    case Cash = 'cash';

    case Card = 'card';

    case Mobile = 'mobile';

    case Online = 'online';
}
