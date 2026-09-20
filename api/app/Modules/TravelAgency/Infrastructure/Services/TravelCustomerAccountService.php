<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Issue #7739 — Comptes clients grand public de la marketplace (épic #7736).
 *
 * Rattachement des réservations et liste « mes réservations » CROSS-AGENCES,
 * strictement ISOLÉES AU CLIENT : chaque requête est bornée par
 * `customer_account_id` (jamais de lecture non bornée), le scope tenant est
 * levé explicitement (`withoutGlobalScope('company')`) car un client
 * n'appartient à aucun tenant — même pattern que TravelMarketplaceService.
 */
final class TravelCustomerAccountService
{
    /**
     * Rattache au compte les réservations MARKETPLACE existantes dont le
     * contact e-mail correspond à l'e-mail du compte (rattachement « à la
     * création » du compte, critère #7739 — la vérification par lien e-mail
     * est un lot ultérieur de l'épic #7736). Seules les réservations encore
     * orphelines (customer_account_id NULL) sont revendiquées : un compte ne
     * vole jamais la réservation d'un autre compte.
     */
    public function claimBookingsByEmail(TravelCustomerAccount $account): int
    {
        return TravelBooking::query()
            ->withoutGlobalScope('company')
            ->whereNull('customer_account_id')
            ->where('booking_source', BookingSource::MARKETPLACE)
            ->whereRaw('LOWER(contact_email) = ?', [mb_strtolower($account->email)])
            ->update(['customer_account_id' => $account->id]);
    }

    /**
     * « Mes réservations » cross-agences : uniquement les réservations du
     * compte (bornage strict par customer_account_id), triées de la plus
     * récente à la plus ancienne, avec trajet/villes/billets pour l'affichage.
     *
     * @return LengthAwarePaginator<int, TravelBooking>
     */
    public function bookingsFor(TravelCustomerAccount $account, int $perPage = 20): LengthAwarePaginator
    {
        return TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('customer_account_id', $account->id)
            // Relations chargées hors contexte tenant (routes publiques : le
            // scope company est inactif sans compagnie courante — même
            // pattern que TravelMarketplaceController::show()).
            ->with(['trip.route.originCity', 'trip.route.destinationCity', 'tickets'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
