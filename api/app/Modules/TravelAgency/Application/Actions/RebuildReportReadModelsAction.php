<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelDailySale;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripOccupancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-506 (#6076) — Recalcul idempotent des read models de rapports.
 *
 * - `travel_daily_sales` : delete + rebuild par tenant dans une transaction
 *   → une reprise du job donne un état identique (aucune accumulation,
 *   aucune ligne orpheline).
 * - `travel_trip_occupancy` : idem par `(company_id, trip_id)`.
 *
 * Tenant-scoped : chaque tenant est traité dans sa propre transaction.
 */
final class RebuildReportReadModelsAction
{
    public function execute(): int
    {
        $affected = 0;

        foreach ($this->companyIds() as $companyId) {
            DB::transaction(function () use ($companyId, &$affected): void {
                $affected += $this->rebuildDailySales((string) $companyId);
                $affected += $this->rebuildTripOccupancy((string) $companyId);
            });
        }

        return $affected;
    }

    /**
     * @return array<int, string>
     */
    private function companyIds(): array
    {
        return TravelDailySale::query()
            ->select('company_id')
            ->distinct()
            ->pluck('company_id')
            ->map(fn ($id): string => (string) $id)
            ->merge(
                TravelTrip::query()->select('company_id')->distinct()->pluck('company_id'),
            )
            ->unique()
            ->values()
            ->all();
    }

    private function rebuildDailySales(string $companyId): int
    {
        TravelDailySale::query()->where('company_id', $companyId)->delete();

        // Agrégation via Query Builder (lignes stdClass, pas de casts Eloquent).
        $rows = DB::table('travel_bookings')
            ->where('company_id', $companyId)
            ->where('status', '!=', BookingStatus::PENDING->value)
            ->selectRaw('DATE(created_at) as sale_date')
            ->selectRaw('booking_source as source')
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as booking_count')
            ->selectRaw('COALESCE(SUM(passenger_count), 0) as passenger_count')
            ->selectRaw('COALESCE(SUM(total_amount_minor), 0) as amount_minor')
            ->selectRaw('COALESCE(MAX(currency), \'XAF\') as currency')
            ->groupBy('sale_date', 'source', 'status')
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            TravelDailySale::query()->create([
                'company_id' => $companyId,
                'sale_date' => (string) $row->sale_date,
                'source' => (string) $row->source,
                'status' => (string) $row->status,
                'currency' => (string) $row->currency,
                'booking_count' => (int) $row->booking_count,
                'passenger_count' => (int) $row->passenger_count,
                'amount_minor' => (int) $row->amount_minor,
            ]);
            $count++;
        }

        return $count;
    }

    private function rebuildTripOccupancy(string $companyId): int
    {
        TravelTripOccupancy::query()->where('company_id', $companyId)->delete();

        $rows = TravelTrip::query()
            ->where('company_id', $companyId)
            ->withCount(['seats as sold' => fn (Builder $s) => $s->where('status', SeatStatus::SOLD)])
            ->withCount(['seats as reserved' => fn (Builder $s) => $s->where('status', SeatStatus::RESERVED)])
            ->get();

        $count = 0;

        foreach ($rows as $trip) {
            /** @var TravelTrip $trip */
            $total = max(0, (int) $trip->total_seats);
            $sold = (int) $trip->getAttribute('sold');
            $reserved = (int) $trip->getAttribute('reserved');
            $free = max(0, $total - $sold - $reserved);

            TravelTripOccupancy::query()->create([
                'company_id' => $companyId,
                'trip_id' => $trip->id,
                'departure_date' => $trip->departure_date->toDateString(),
                'total_seats' => $total,
                'sold_seats' => $sold,
                'reserved_seats' => $reserved,
                'free_seats' => $free,
                'occupancy_rate' => $total > 0 ? round($sold / $total, 4) : 0.0,
            ]);
            $count++;
        }

        return $count;
    }
}
