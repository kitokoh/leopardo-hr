<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPublicCustomer;

/**
 * #7739 — Rattache au compte client les réservations INVITÉES qui portent
 * son adresse email de contact.
 *
 * Appelée à la création du compte puis à chaque connexion (idempotent) :
 * l'email du compte est la preuve de possession — seules les réservations
 * encore ORPHELINES (`public_customer_id` NULL) sont revendiquées, une
 * réservation déjà rattachée à un autre compte n'est JAMAIS déplacée.
 *
 * Cross-tenant assumé (`withoutGlobalScope('company')`) : les réservations
 * du client vivent chez plusieurs agences ; seul `public_customer_id` est
 * écrit, jamais `company_id`.
 */
class ClaimPublicCustomerBookingsAction
{
    /**
     * @return int Nombre de réservations rattachées.
     */
    public function execute(TravelPublicCustomer $customer): int
    {
        return TravelBooking::query()
            ->withoutGlobalScope('company')
            ->whereNull('public_customer_id')
            ->whereRaw('LOWER(contact_email) = ?', [mb_strtolower($customer->email)])
            ->update(['public_customer_id' => $customer->id]);
    }
}
