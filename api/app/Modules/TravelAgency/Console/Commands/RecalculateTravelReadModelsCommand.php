<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelDailySale;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripOccupancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-506 (#6076) — Recalcul des read models de reporting travel.
 *
 * Upsert idempotent par clé unique : une reprise du job produit le MÊME
 * état (aucun doublon, mêmes valeurs). Itère tous les tenants actifs
 * (pattern AutoCloseAttendanceCommand).
 */
class RecalculateTravelReadModelsCommand extends Command
{
    protected $signature = 'travel:recalculate-read-models
                                {--company= : Cibler un tenant précis}
                                {--from= : Borne basse (YYYY-MM-DD, défaut -30 j)}';

    protected $description = 'Recalculate travel reporting read models (TRAVEL-506)';

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No active company — nothing to recalculate.');

            return self::SUCCESS;
        }

        $from = $this->option('from') ? (string) $this->option('from') : now()->subDays(30)->toDateString();

        $total = 0;

        foreach ($companies as $company) {
            $count = $tenantManager->withinTenant(
                $company,
                fn (): int => $this->recalculateTenant((string) $company->id, $from),
            );

            $this->info("Tenant {$company->id}: {$count} ligne(s) de read model recalculée(s).");
            $total += $count;
        }

        $this->info("Total: {$total} ligne(s).");

        return self::SUCCESS;
    }

    private function recalculateTenant(string $companyId, string $from): int
    {
        return DB::transaction(function () use ($companyId, $from): int {
            $count = 0;

            // 1. Ventes journalières par trajet.
            $rows = TravelBooking::query()
                ->where('company_id', $companyId)
                ->where('created_at', '>=', $from)
                ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::REFUNDED->value])
                ->get(['created_at', 'trip_id', 'passenger_count', 'total_amount_minor']);

            $byKey = [];

            foreach ($rows as $row) {
                $date = $row->created_at?->toDateString() ?? now()->toDateString();
                $key = $date.'|'.$row->trip_id;
                $byKey[$key] = $byKey[$key] ?? ['bookings' => 0, 'passengers' => 0, 'revenue' => 0];
                $byKey[$key]['bookings']++;
                $byKey[$key]['passengers'] += (int) $row->passenger_count;
                $byKey[$key]['revenue'] += (int) $row->total_amount_minor;
            }

            foreach ($byKey as $key => $agg) {
                [$date, $tripId] = explode('|', $key);

                TravelDailySale::query()->updateOrCreate(
                    ['company_id' => $companyId, 'sale_date' => $date, 'trip_id' => (int) $tripId],
                    [
                        'bookings_count' => $agg['bookings'],
                        'passengers_count' => $agg['passengers'],
                        'revenue_minor' => $agg['revenue'],
                    ],
                );

                $count++;
            }

            // 2. Occupation par trajet.
            $trips = TravelTrip::query()
                ->where('company_id', $companyId)
                ->where('departure_date', '>=', $from)
                ->get(['id', 'departure_date', 'total_seats']);

            foreach ($trips as $trip) {
                $sold = (int) TravelBooking::query()
                    ->where('company_id', $companyId)
                    ->where('trip_id', $trip->id)
                    ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::REFUNDED->value])
                    ->sum('passenger_count');

                $total = max(1, (int) $trip->total_seats);

                TravelTripOccupancy::query()->updateOrCreate(
                    ['company_id' => $companyId, 'trip_id' => $trip->id],
                    [
                        'departure_date' => $trip->departure_date?->toDateString(),
                        'seats_sold' => $sold,
                        'total_seats' => $total,
                        'occupancy_rate' => round($sold / $total, 4),
                    ],
                );

                $count++;
            }

            return $count;
        });
    }
}
