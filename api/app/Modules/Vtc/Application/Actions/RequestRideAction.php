<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideRequested;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\ValueObjects\IdempotencyKey;
use App\Modules\Vtc\Domain\ValueObjects\RideReference;
use App\Shared\Geo\GeoPoint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Création IDEMPOTENTE d'une course VTC (BC-34 VTC, VTC-03/#8359).
 *
 * Garanties (spec §5.1/§7) :
 *  - même Idempotency-Key → la course EXISTANTE est retournée (rejeu
 *    transparent, zéro doublon — contrainte UNIQUE(company_id,
 *    idempotency_key) en filet de sécurité sous concurrence) ;
 *  - devis persisté sur la course (distance, durée, prix en minor units) ;
 *  - statut initial `requested` → `dispatching` immédiat (le dispatch
 *    VTC-04, branché sur VtcRideRequested, prend le relais) ;
 *  - journal append-only : `ride.requested` + `dispatch.started` ;
 *  - événement de domaine VtcRideRequested émis après commit logique.
 *
 * La référence VTC-YYYY-NNNNNN est séquencée par tenant et par année ; une
 * collision sous concurrence est résorbée par re-comptage (3 tentatives).
 */
final class RequestRideAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly EstimateRideFareAction $estimateFare,
    ) {}

    /**
     * @param  array{passenger_user_id: int|null, passenger_name: string|null, passenger_phone: string|null, pickup_address: string|null, dropoff_address: string|null, fare_profile_id: int|null}  $data
     * @return array{0: VtcRide, 1: bool} [course, true si rejeu idempotent]
     */
    public function execute(array $data, GeoPoint $pickup, GeoPoint $dropoff, IdempotencyKey $key): array
    {
        $existing = $this->findByIdempotencyKey($key);

        if ($existing instanceof VtcRide) {
            return [$existing, true];
        }

        $estimate = $this->estimateFare->execute($pickup, $dropoff, $data['fare_profile_id']);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $ride = DB::transaction(function () use ($data, $pickup, $dropoff, $key, $estimate): VtcRide {
                    /** @var VtcRide $ride */
                    $ride = VtcRide::query()->create([
                        'reference' => $this->nextReference()->toString(),
                        'passenger_user_id' => $data['passenger_user_id'],
                        'passenger_name' => $data['passenger_name'],
                        'passenger_phone' => $data['passenger_phone'],
                        'pickup_latitude' => $pickup->latitude,
                        'pickup_longitude' => $pickup->longitude,
                        'pickup_address' => $data['pickup_address'],
                        'dropoff_latitude' => $dropoff->latitude,
                        'dropoff_longitude' => $dropoff->longitude,
                        'dropoff_address' => $data['dropoff_address'],
                        'status' => VtcRideStatus::Dispatching->value,
                        'fare_profile_id' => $estimate->fareProfileId,
                        'estimated_distance_m' => $estimate->roadDistanceMeters,
                        'estimated_duration_s' => $estimate->durationSeconds,
                        'estimated_price_minor' => $estimate->priceMinor,
                        'currency' => $estimate->currency,
                        'requested_at' => now(),
                        'idempotency_key' => $key->toString(),
                    ]);

                    $this->journal($ride, VtcRideEventType::RideRequested, [
                        'reference' => $ride->reference,
                        'passenger_user_id' => $ride->passenger_user_id,
                        'estimated_price_minor' => $ride->estimated_price_minor,
                        'currency' => $ride->currency,
                    ]);

                    $this->journal($ride, VtcRideEventType::DispatchStarted, [
                        'pickup' => $pickup->toArray(),
                    ]);

                    return $ride;
                });

                VtcRideRequested::dispatch(
                    (string) $ride->company_id,
                    $ride->id,
                    $ride->reference,
                    $ride->passenger_user_id,
                );

                return [$ride, false];
            } catch (QueryException $exception) {
                // Doublon d'idempotence sous concurrence : le rejeu gagne.
                $existing = $this->findByIdempotencyKey($key);

                if ($existing instanceof VtcRide) {
                    return [$existing, true];
                }

                // Collision de référence (séquenceur) : re-compter et réessayer.
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        // Inatteignable (la boucle retourne ou lève) — garde PHPStan.
        throw new \RuntimeException((string) __('vtc.ride_creation_failed', ['attempts' => self::MAX_ATTEMPTS]));
    }

    private function findByIdempotencyKey(IdempotencyKey $key): ?VtcRide
    {
        /** @var VtcRide|null $ride */
        $ride = VtcRide::query()
            ->where('idempotency_key', $key->toString())
            ->first();

        return $ride;
    }

    private function nextReference(): RideReference
    {
        $year = (int) now()->format('Y');

        $sequence = VtcRide::query()
            ->where('reference', 'like', sprintf('VTC-%d-%%', $year))
            ->count() + 1;

        return RideReference::fromSequence($year, $sequence);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function journal(VtcRide $ride, VtcRideEventType $type, array $payload): void
    {
        VtcRideEvent::create([
            'ride_id' => $ride->id,
            'type' => $type->value,
            'payload' => $payload,
        ]);
    }
}
