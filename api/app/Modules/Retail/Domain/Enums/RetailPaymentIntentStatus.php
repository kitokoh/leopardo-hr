<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut d'un intent de paiement en ligne marketplace (BC-17 RETAIL, #7812).
 *
 * `pending` → `paid` (webhook signé) ou `failed` (échec PSP) ; `cancelled`
 * quand la commande est annulée avant encaissement. Un seul intent `pending`
 * par commande (index unique partiel) — le rejeu de la demande de paiement
 * retourne l'intent existant.
 */
enum RetailPaymentIntentStatus: string
{
    case Pending = 'pending';

    case Paid = 'paid';

    case Failed = 'failed';

    case Cancelled = 'cancelled';
}
