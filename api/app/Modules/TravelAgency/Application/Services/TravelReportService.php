<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Services;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Carbon\CarbonInterface;

/**
 * TRAVEL-501..504 / 507 (#6071..#6074, #6077) — Agrégats de pilotage travel.
 *
 * Tous les agrégats sont calculés SERVEUR en minor units, scopés tenant
 * (+ filtres période/trajet/route/source), et cohérents avec les données
 * sous-jacentes :
 * - sales         : réservations (count + montant) par période/source/statut ;
 * - occupancy     : taux = sièges vendus / total par trajet ;
 * - revenue       : paiements confirmés − remboursements ;
 * - cancellations : annulations par période/source (motif non porté par le
 *   schéma v1 — regroupées par source + statut) ;
 * - dashboard     : vue du jour (ventes, passagers, recettes, occupation,
 *   annulations).
 *
 * Permission `travel.reports` (Gate — tous les rôles opérationnels).
 */
final class TravelReportService
{
    /**
     * Ventes : réservations non annulées sur période.
     *
     * @return array<string, mixed>
     */
    public function sales(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $tripId = null,
        ?int $routeId = null,
        ?string $source = null,
        ?string $status = null,
    ): array {
        $query = TravelBooking::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to]);

        if ($tripId !== null) {
            $query->where('trip_id', $tripId);
        }

        if ($source !== null) {
            $query->where('booking_source', $source);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($routeId !== null) {
            $query->whereHas('trip', fn ($q) => $q->where('route_id', $routeId));
        }

        $bookings = $query->get(['status', 'booking_source', 'passenger_count', 'total_amount_minor']);

        $count = 0;
        $passengers = 0;
        $revenueMinor = 0;
        $bySource = [];
        $byStatus = [];

        foreach ($bookings as $booking) {
            if (in_array($booking->status, [BookingStatus::CANCELLED, BookingStatus::REFUNDED], true)) {
                continue; // ventes nettes : annulées/remboursées exclues
            }

            $count++;
            $passengers += (int) $booking->passenger_count;
            $revenueMinor += (int) $booking->total_amount_minor;

            $src = $booking->booking_source->value;
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;

            $st = $booking->status->value;
            $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'filters' => [
                'trip_id' => $tripId,
                'route_id' => $routeId,
                'source' => $source,
                'status' => $status,
            ],
            'bookings_count' => $count,
            'passengers_count' => $passengers,
            'revenue_minor' => $revenueMinor,
            'by_source' => $bySource,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Occupation par trajet (taux = sièges vendus / capacité, période).
     *
     * @return array<string, mixed>
     */
    public function occupancy(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $tripId = null,
        ?int $routeId = null,
    ): array {
        $query = TravelTrip::query()
            ->where('company_id', $companyId)
            ->whereBetween('departure_date', [$from->toDateString(), $to->toDateString()]);

        if ($tripId !== null) {
            $query->where('id', $tripId);
        }

        if ($routeId !== null) {
            $query->where('route_id', $routeId);
        }

        $trips = $query->get(['id', 'code', 'route_id', 'departure_date', 'total_seats']);

        $rows = [];

        foreach ($trips as $trip) {
            $sold = (int) TravelBooking::query()
                ->where('company_id', $companyId)
                ->where('trip_id', $trip->id)
                ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::REFUNDED->value])
                ->sum('passenger_count');

            $total = max(1, (int) $trip->total_seats);

            $rows[] = [
                'trip_id' => $trip->id,
                'code' => $trip->code,
                'route_id' => $trip->route_id,
                'departure_date' => $trip->departure_date?->toDateString(),
                'seats_sold' => $sold,
                'total_seats' => $total,
                'occupancy_rate' => round($sold / $total, 4),
            ];
        }

        // Tri par taux décroissant.
        usort($rows, fn (array $a, array $b): int => $b['occupancy_rate'] <=> $a['occupancy_rate']);

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'trip_id' => $tripId,
            'route_id' => $routeId,
            'trips_count' => count($rows),
            'by_trip' => $rows,
        ];
    }

    /**
     * Recettes : paiements confirmés − remboursements (période).
     *
     * @return array<string, mixed>
     */
    public function revenue(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $tripId = null,
        ?string $source = null,
    ): array {
        $payments = TravelPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->when($tripId !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('trip_id', $tripId)))
            ->when($source !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('booking_source', $source)))
            ->get(['booking_id', 'amount_minor', 'status']);

        $confirmed = 0;
        $refunded = 0;
        $byStatus = [];

        foreach ($payments as $payment) {
            $status = $payment->status;

            if ($status === PaymentStatus::CONFIRMED) {
                $confirmed += (int) $payment->amount_minor;
            } elseif ($status === PaymentStatus::REFUNDED) {
                $refunded += (int) $payment->amount_minor;
            }

            $key = $status->value;
            $byStatus[$key] = ($byStatus[$key] ?? 0) + (int) $payment->amount_minor;
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'trip_id' => $tripId,
            'source' => $source,
            'confirmed_minor' => $confirmed,
            'refunded_minor' => $refunded,
            'net_minor' => $confirmed - $refunded,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Annulations sur période (par source/statut — le motif n'est pas porté
     * par le schéma v1 des réservations).
     *
     * @return array<string, mixed>
     */
    public function cancellations(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $tripId = null,
        ?string $source = null,
    ): array {
        $query = TravelBooking::query()
            ->where('company_id', $companyId)
            ->where('status', BookingStatus::CANCELLED->value)
            ->whereBetween('updated_at', [$from, $to]);

        if ($tripId !== null) {
            $query->where('trip_id', $tripId);
        }

        if ($source !== null) {
            $query->where('booking_source', $source);
        }

        $bookings = $query->get(['booking_source', 'passenger_count', 'total_amount_minor']);

        $count = 0;
        $passengers = 0;
        $amountMinor = 0;
        $bySource = [];

        foreach ($bookings as $booking) {
            $count++;
            $passengers += (int) $booking->passenger_count;
            $amountMinor += (int) $booking->total_amount_minor;

            $src = $booking->booking_source->value;
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'trip_id' => $tripId,
            'source' => $source,
            'cancellations_count' => $count,
            'passengers_count' => $passengers,
            'amount_minor' => $amountMinor,
            'by_source' => $bySource,
        ];
    }

    /**
     * Dashboard KPIs (vue du jour, période configurable en jours).
     *
     * @return array<string, mixed>
     */
    public function dashboard(string $companyId, ?int $tripId = null, int $days = 1): array
    {
        $from = now()->startOfDay()->subDays(max(0, $days - 1));
        $to = now()->endOfDay();

        $sales = $this->sales($companyId, $from, $to, $tripId);
        $revenue = $this->revenue($companyId, $from, $to, $tripId);
        $cancellations = $this->cancellations($companyId, $from, $to, $tripId);
        $occupancy = $this->occupancy($companyId, $from, $to, $tripId);

        $tripsCount = count($occupancy['by_trip']);
        $soldTotal = 0;
        $capacityTotal = 0;
        foreach ($occupancy['by_trip'] as $row) {
            $soldTotal += $row['seats_sold'];
            $capacityTotal += $row['total_seats'];
        }

        return [
            'date' => now()->toDateString(),
            'period_days' => $days,
            'trip_id' => $tripId,
            'sales_today' => $sales['bookings_count'],
            'passengers' => $sales['passengers_count'],
            'revenue_minor' => $revenue['net_minor'],
            'confirmed_minor' => $revenue['confirmed_minor'],
            'cancellations' => $cancellations['cancellations_count'],
            'occupancy_rate' => $capacityTotal > 0 ? round($soldTotal / $capacityTotal, 4) : 0.0,
            'trips_count' => $tripsCount,
        ];
    }
}
