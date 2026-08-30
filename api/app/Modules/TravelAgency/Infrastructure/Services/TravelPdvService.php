<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCashSession;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-810 (#6100) — Point de vente tablette.
 *
 * - Ouverture : une seule session ouverte par tenant (422 sinon) ;
 * - Clôture : ATTENDU = solde initial + paiements cash CONFIRMÉS depuis
 *   l'ouverture, comparé au RÉEL saisi → écart calculé serveur (critère
 *   d'acceptation) ;
 * - Reçu : données d'encaissement d'une réservation (impression PDF via le
 *   générateur de billets existant, TRAVEL-412).
 */
final class TravelPdvService
{
    public function open(string $companyId, Employee $actor, int $openingBalanceMinor): TravelCashSession
    {
        $open = TravelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', TravelCashSession::STATUS_OPEN)
            ->first();

        if ($open instanceof TravelCashSession) {
            abort(422, 'Une session de caisse est déjà ouverte pour ce tenant.');
        }

        /** @var TravelCashSession $session */
        $session = TravelCashSession::query()->create([
            'company_id' => $companyId,
            'opened_by_user_id' => $actor->id,
            'opened_at' => now(),
            'opening_balance_minor' => max(0, $openingBalanceMinor),
            'status' => TravelCashSession::STATUS_OPEN,
        ]);

        return $session;
    }

    public function current(string $companyId): ?TravelCashSession
    {
        /** @var TravelCashSession|null $session */
        $session = TravelCashSession::query()
            ->where('company_id', $companyId)
            ->where('status', TravelCashSession::STATUS_OPEN)
            ->first();

        return $session;
    }

    /**
     * Clôture : attendu calculé SERVEUR, écart = réel − attendu.
     */
    public function close(string $companyId, Employee $actor, int $actualBalanceMinor): TravelCashSession
    {
        $session = $this->current($companyId);

        if (! $session instanceof TravelCashSession) {
            abort(422, 'Aucune session de caisse ouverte.');
        }

        $expected = (int) $session->opening_balance_minor + $this->cashPaidSince($companyId, $session->opened_at);

        return DB::transaction(function () use ($session, $actualBalanceMinor, $expected): TravelCashSession {
            $session->forceFill([
                'status' => TravelCashSession::STATUS_CLOSED,
                'closed_at' => now(),
                'expected_balance_minor' => $expected,
                'actual_balance_minor' => max(0, $actualBalanceMinor),
                'difference_minor' => $actualBalanceMinor - $expected,
            ])->save();

            return $session->refresh();
        });
    }

    public function cashPaidSince(string $companyId, \DateTimeInterface $since): int
    {
        return (int) TravelPayment::query()
            ->where('company_id', $companyId)
            ->where('provider_code', 'cash')
            ->where('status', PaymentStatus::CONFIRMED)
            ->where('created_at', '>=', $since)
            ->sum('amount_minor');
    }

    /**
     * Reçu d'une réservation (données d'encaissement).
     *
     * @return array<string, mixed>
     */
    public function receipt(string $companyId, TravelBooking $booking): array
    {
        if ($booking->company_id !== $companyId) {
            abort(404);
        }

        $booking->load('passengers');

        return [
            'reference' => $booking->reference,
            'trip_id' => $booking->trip_id,
            'passenger_count' => $booking->passenger_count,
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'payment_status' => $booking->payment_status->value,
            'passengers' => $booking->passengers->map(fn ($p): array => [
                'full_name' => $p->full_name,
                'seat_number' => $p->seat_number,
                'unit_price_minor' => $p->unit_price_minor,
            ])->all(),
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
