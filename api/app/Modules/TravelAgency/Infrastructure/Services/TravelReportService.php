<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
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
        ?int $officeId = null,
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

        // #8545 — ventilation par agence. `office_id = null` n'est PAS un
        // filtre « agence » : une vente en ligne n'a pas d'agence, elle est
        // exposée séparément (ligne `unassigned` de byOffices()).
        if ($officeId !== null) {
            $query->where('office_id', $officeId);
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
                'office_id' => $officeId,
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
                'departure_date' => $trip->departure_date->toDateString(),
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
        ?int $officeId = null,
    ): array {
        $payments = TravelPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->when($tripId !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('trip_id', $tripId)))
            ->when($source !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('booking_source', $source)))
            ->when($officeId !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('office_id', $officeId)))
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
            'office_id' => $officeId,
            'confirmed_minor' => $confirmed,
            'refunded_minor' => $refunded,
            'net_minor' => $confirmed - $refunded,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Annulations sur période (par source + par motif — TRAVEL-504
     * « annulations + motifs agrégés » ; `cancel_reason` est requêtable
     * depuis #6074 — le docblock précédent était obsolète, #8128).
     *
     * @return array<string, mixed>
     */
    public function cancellations(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $tripId = null,
        ?string $source = null,
        ?int $officeId = null,
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

        if ($officeId !== null) {
            $query->where('office_id', $officeId);
        }

        $bookings = $query->get(['booking_source', 'passenger_count', 'total_amount_minor', 'cancel_reason']);

        $count = 0;
        $passengers = 0;
        $amountMinor = 0;
        $bySource = [];
        /** @var array<string, int> $byReason */
        $byReason = [];

        foreach ($bookings as $booking) {
            $count++;
            $passengers += (int) $booking->passenger_count;
            $amountMinor += (int) $booking->total_amount_minor;

            $src = $booking->booking_source->value;
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;

            $reason = trim((string) ($booking->cancel_reason ?? ''));
            if ($reason !== '') {
                $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
            }
        }

        // Motifs les plus fréquents d'abord (contrat de lecture TRAVEL-504).
        arsort($byReason);

        $byReasonList = [];
        foreach ($byReason as $reason => $reasonCount) {
            $byReasonList[] = ['reason' => $reason, 'count' => $reasonCount];
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'trip_id' => $tripId,
            'source' => $source,
            'office_id' => $officeId,
            'cancellations_count' => $count,
            'passengers_count' => $passengers,
            'amount_minor' => $amountMinor,
            'by_source' => $bySource,
            'by_reason' => $byReasonList,
        ];
    }

    /**
     * #8545 (TRAVEL-OS) — VUE CONSOLIDÉE PAR AGENCE.
     *
     * Une ligne par bureau de vente du tenant (réservations, billets, recettes
     * nettes, annulations) + un bloc `totals` = somme des agences.
     *
     * Les réservations SANS agence (`office_id = null`, c'est-à-dire les ventes
     * en ligne) apparaissent dans une ligne `unassigned` EXPLICITE : elles ne
     * sont jamais silencieusement absorbées dans une agence, ce qui ferait
     * diverger `totals` du total entreprise.
     *
     * Les montants sont recalculés serveur en minor units — aucun flottant.
     *
     * @return array<string, mixed>
     */
    public function byOffices(
        string $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $source = null,
    ): array {
        $base = fn (): \Illuminate\Database\Eloquent\Builder => TravelBooking::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->when($source !== null, fn ($q) => $q->where('booking_source', $source));

        $sold = $base()
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::REFUNDED->value])
            ->get(['office_id', 'passenger_count', 'total_amount_minor']);

        $cancelled = $base()
            ->where('status', BookingStatus::CANCELLED->value)
            ->get(['office_id']);

        $offices = TravelOffice::query()
            ->where('company_id', $companyId)
            ->with('city:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'city_id']);

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        $blank = static fn (string $key, string $name): array => [
            'office_id' => $key === 'unassigned' ? null : (int) $key,
            'name' => $name,
            'city' => null,
            'bookings_count' => 0,
            'passengers_count' => 0,
            'revenue_minor' => 0,
            'cancellations_count' => 0,
        ];

        foreach ($offices as $office) {
            $row = $blank((string) $office->id, (string) $office->name);
            $row['city'] = $office->city?->name;
            $rows[(string) $office->id] = $row;
        }

        // Ligne explicite pour les ventes sans agence (en ligne).
        $rows['unassigned'] = $blank('unassigned', 'Sans agence (ventes en ligne)');

        foreach ($sold as $booking) {
            $key = $booking->office_id === null ? 'unassigned' : (string) $booking->office_id;

            if (! isset($rows[$key])) {
                // Agence supprimée après la vente : la vente existe, elle doit
                // rester comptée — jamais silencieusement perdue.
                $rows[$key] = $blank($key, 'Agence supprimée #'.$key);
            }

            $rows[$key]['bookings_count']++;
            $rows[$key]['passengers_count'] += (int) $booking->passenger_count;
            $rows[$key]['revenue_minor'] += (int) $booking->total_amount_minor;
        }

        foreach ($cancelled as $booking) {
            $key = $booking->office_id === null ? 'unassigned' : (string) $booking->office_id;

            if (! isset($rows[$key])) {
                $rows[$key] = $blank($key, 'Agence supprimée #'.$key);
            }

            $rows[$key]['cancellations_count']++;
        }

        $totals = [
            'bookings_count' => 0,
            'passengers_count' => 0,
            'revenue_minor' => 0,
            'cancellations_count' => 0,
        ];

        foreach ($rows as $row) {
            $totals['bookings_count'] += $row['bookings_count'];
            $totals['passengers_count'] += $row['passengers_count'];
            $totals['revenue_minor'] += $row['revenue_minor'];
            $totals['cancellations_count'] += $row['cancellations_count'];
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'source' => $source,
            'offices' => array_values($rows),
            'totals' => $totals,
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
