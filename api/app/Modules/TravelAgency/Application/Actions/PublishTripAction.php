<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-310 (#6040) — Publication d'un trajet (draft|scheduled → published).
 *
 * Transactionnelle : transition d'état validée, `published_at` horodaté,
 * événement outbox `travel.trip.published.v1` publié APRÈS commit (jamais
 * dans la transaction). Un trajet déjà publié est idempotent (rejeu sûr).
 */
final class PublishTripAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelTrip $trip, Employee $actor): TravelTrip
    {
        if ($trip->status === TripStatus::PUBLISHED) {
            return $trip; // Idempotence : rejeu sans effet de bord.
        }

        if (! in_array($trip->status, [TripStatus::DRAFT, TripStatus::SCHEDULED], true)) {
            abort(422, 'Impossible de publier un trajet '.$trip->status->value.'.');
        }

        DB::transaction(function () use ($trip): void {
            $trip->forceFill([
                'status' => TripStatus::PUBLISHED,
                'published_at' => now(),
            ])->save();
        });

        $this->outbox->publish($trip->company_id, 'travel.trip.published.v1', [
            'trip_id' => $trip->id,
            'trip_code' => $trip->code,
            'published_by' => $actor->id,
            'published_at' => now()->toIso8601String(),
        ]);

        return $trip;
    }
}
