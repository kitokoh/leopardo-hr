<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Services;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideAccepted;
use App\Modules\Vtc\Domain\Events\VtcRideExpired;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Exceptions\VtcOfferNotCurrentException;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;
use App\Modules\Vtc\Infrastructure\Jobs\ExpireVtcOfferJob;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Support\Facades\DB;

/**
 * Moteur de dispatch VTC (BC-34 VTC, VTC-04/#8360) — le cœur métier.
 *
 * Matching (spec §5.3) : chauffeurs `available` du MÊME TENANT dans le rayon
 * `vtc.dispatch.radius_km`, triés du plus proche au plus lointain par le
 * CORE GÉOSPATIAL (BC-33 — type `vtc_driver` de la registry, PostGIS
 * ST_DWithin + KNN ou repli interne ; vtc ne calcule JAMAIS de distance).
 *
 * Cascade d'offres séquentielles :
 *   1. `offerToNextCandidate` journalise `dispatch.offer_sent`, pose l'offre
 *      courante en metadata (source de vérité) et programme
 *      ExpireVtcOfferJob (timeout `vtc.dispatch.offer_timeout_s`, 30 s) ;
 *   2. timeout → `expireOffer` (idempotent : l'offre doit être encore
 *      courante) → offre au suivant ; déclinaison → suivant immédiat ;
 *   3. acceptation → `acceptOffer` SOUS VERROU (lockForUpdate) : une double
 *      acceptation est impossible, l'offre doit être courante ;
 *   4. cascade épuisée (`vtc.dispatch.max_offers`, 5) ou aucun candidat →
 *      `expireRide` : statut `expired`, journal `dispatch.exhausted`,
 *      événement VtcRideExpired (notification passager).
 *
 * Chaque transition est verrouillée par VtcRideStateMachine et journalisée
 * dans `vtc_ride_events` (append-only) — aucune course orpheline.
 */
final class VtcDispatchService
{
    /** Clé metadata de l'offre courante (source de vérité accept/timeout). */
    private const PENDING_OFFER_KEY = 'pending_offer';

    public function __construct(
        private readonly GeoServiceContract $geo,
        private readonly VtcRideStateMachine $stateMachine,
    ) {
    }

    /**
     * Offre la course au chauffeur disponible le plus proche n'ayant pas
     * encore été sollicité, ou expire la course si la cascade est épuisée.
     */
    public function offerToNextCandidate(VtcRide $ride): void
    {
        if ($ride->status !== VtcRideStatus::Dispatching) {
            return;
        }

        $offersSent = $this->offeredDriverIds($ride);

        if (count($offersSent) >= $this->maxOffers()) {
            $this->expireRide($ride, 'max_offers_reached');

            return;
        }

        $candidate = $this->findNextCandidate($ride, $offersSent);

        if ($candidate === null) {
            $this->expireRide($ride, 'no_available_driver_in_radius');

            return;
        }

        $seq = count($offersSent) + 1;
        $driverId = $candidate['driver_id'];
        $timeoutSeconds = $this->offerTimeoutSeconds();

        $offered = DB::transaction(function () use ($ride, $driverId, $seq, $candidate, $timeoutSeconds): bool {
            /** @var VtcRide|null $locked */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->first();

            // Une annulation/acceptation a pu intervenir entre la lecture et
            // le verrou : l'offre ne part jamais sur une course non dispatching.
            if (! $locked instanceof VtcRide || $locked->status !== VtcRideStatus::Dispatching) {
                return false;
            }

            $metadata = $locked->metadata ?? [];
            $metadata[self::PENDING_OFFER_KEY] = [
                'driver_id' => $driverId,
                'seq' => $seq,
                'sent_at' => now()->toIso8601String(),
                'expires_at' => now()->addSeconds($timeoutSeconds)->toIso8601String(),
            ];

            $locked->forceFill(['metadata' => $metadata])->save();

            $this->journal($locked, VtcRideEventType::OfferSent, [
                'driver_id' => $driverId,
                'seq' => $seq,
                'distance_meters' => $candidate['distance_meters'],
            ]);

            return true;
        });

        if ($offered) {
            ExpireVtcOfferJob::dispatch((string) $ride->company_id, $ride->id, $driverId, $seq)
                ->delay(now()->addSeconds($timeoutSeconds));
        }
    }

    /**
     * Timeout d'une offre (job différé) : ne fait quelque chose QUE si
     * l'offre est encore la courante (idempotence face aux rejeus/doublons
     * de file) — puis enchaîne sur le candidat suivant.
     */
    public function expireOffer(string $companyId, int $rideId, int $driverId, int $seq): void
    {
        $ride = $this->expirePendingOffer($companyId, $rideId, $driverId, $seq, VtcRideEventType::OfferExpired);

        if ($ride instanceof VtcRide) {
            $this->offerToNextCandidate($ride);
        }
    }

    /**
     * Déclinaison par le chauffeur (API VTC-05) : l'offre doit être la
     * courante — passage immédiat au candidat suivant (sans attendre le
     * timeout).
     */
    public function declineOffer(VtcRide $ride, int $driverId): void
    {
        $pending = $this->pendingOffer($ride);

        if ($pending === null || $pending['driver_id'] !== $driverId) {
            throw new VtcOfferNotCurrentException($ride->id, $driverId);
        }

        $updated = $this->expirePendingOffer(
            (string) $ride->company_id,
            $ride->id,
            $driverId,
            $pending['seq'],
            VtcRideEventType::OfferDeclined
        );

        if ($updated instanceof VtcRide) {
            $this->offerToNextCandidate($updated);
        }
    }

    /**
     * Acceptation SOUS VERROU (critère « double acceptation impossible ») :
     * la course doit être `dispatching` ET l'offre courante doit appartenir
     * au chauffeur acceptant — sinon 409.
     *
     * @throws InvalidRideTransitionException|VtcOfferNotCurrentException
     */
    public function acceptOffer(VtcRide $ride, int $driverId): VtcRide
    {
        $accepted = DB::transaction(function () use ($ride, $driverId): VtcRide {
            /** @var VtcRide|null $locked */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->first();

            if (! $locked instanceof VtcRide) {
                throw new VtcOfferNotCurrentException($ride->id, $driverId);
            }

            if ($locked->status !== VtcRideStatus::Dispatching) {
                throw new InvalidRideTransitionException($locked->status, VtcRideStatus::Accepted);
            }

            $pending = $this->pendingOffer($locked);

            if ($pending === null || $pending['driver_id'] !== $driverId) {
                throw new VtcOfferNotCurrentException($locked->id, $driverId);
            }

            $this->stateMachine->assertCanTransitionTo($locked->status, VtcRideStatus::Accepted);

            $metadata = $locked->metadata ?? [];
            unset($metadata[self::PENDING_OFFER_KEY]);

            $locked->forceFill([
                'status' => VtcRideStatus::Accepted->value,
                'driver_id' => $driverId,
                'accepted_at' => now(),
                'metadata' => $metadata,
            ])->save();

            // Le chauffeur devient busy : hors matching jusqu'à la clôture.
            VtcDriver::query()
                ->whereKey($driverId)
                ->update(['status' => VtcDriverStatus::Busy->value]);

            $this->journal($locked, VtcRideEventType::RideAccepted, [
                'driver_id' => $driverId,
                'seq' => $pending['seq'],
            ]);

            return $locked;
        });

        VtcRideAccepted::dispatch(
            (string) $accepted->company_id,
            $accepted->id,
            $accepted->reference,
            $driverId,
        );

        return $accepted->refresh();
    }

    /**
     * Expire la course (cascade épuisée ou aucun candidat) — idempotent :
     * une course sortie de dispatching entre-temps n'est pas touchée.
     */
    public function expireRide(VtcRide $ride, string $reason): void
    {
        $expired = DB::transaction(function () use ($ride, $reason): ?VtcRide {
            /** @var VtcRide|null $locked */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->first();

            if (! $locked instanceof VtcRide
                || ! in_array($locked->status, [VtcRideStatus::Requested, VtcRideStatus::Dispatching], true)) {
                return null;
            }

            $this->stateMachine->assertCanTransitionTo($locked->status, VtcRideStatus::Expired);

            $metadata = $locked->metadata ?? [];
            unset($metadata[self::PENDING_OFFER_KEY]);

            $locked->forceFill([
                'status' => VtcRideStatus::Expired->value,
                'expired_at' => now(),
                'metadata' => $metadata,
            ])->save();

            $this->journal($locked, VtcRideEventType::DispatchExhausted, [
                'reason' => $reason,
                'offers_sent' => count($this->offeredDriverIds($locked)),
            ]);

            return $locked;
        });

        if ($expired instanceof VtcRide) {
            VtcRideExpired::dispatch(
                (string) $expired->company_id,
                $expired->id,
                $expired->reference,
                $reason,
            );
        }
    }

    /**
     * Chauffeurs déjà sollicités pour cette course (journal append-only).
     *
     * @return list<int>
     */
    private function offeredDriverIds(VtcRide $ride): array
    {
        $ids = [];

        foreach ($ride->events()->where('type', VtcRideEventType::OfferSent->value)->get() as $event) {
            $driverId = $event->payload['driver_id'] ?? null;

            if (is_numeric($driverId)) {
                $ids[] = (int) $driverId;
            }
        }

        return $ids;
    }

    /**
     * Candidat suivant : plus proche disponible (core geo) hors déjà
     * sollicités — la fenêtre de recherche couvre toute la cascade
     * (`max_offers` candidats max par course).
     *
     * @param  list<int>  $excludeDriverIds
     * @return array{driver_id: int, distance_meters: int}|null
     */
    private function findNextCandidate(VtcRide $ride, array $excludeDriverIds): ?array
    {
        $pickup = new GeoPoint($ride->pickup_latitude, $ride->pickup_longitude);

        $candidates = $this->geo->nearest('vtc_driver', $pickup, $this->radiusKm(), $this->maxOffers());

        foreach ($candidates as $candidate) {
            $payload = $candidate->toArray();
            $driverId = $payload['id'];

            if (is_int($driverId) && ! in_array($driverId, $excludeDriverIds, true)) {
                return [
                    'driver_id' => $driverId,
                    'distance_meters' => $payload['distance_meters'],
                ];
            }
        }

        return null;
    }

    /**
     * Verrouille et consomme l'offre courante si (et seulement si) elle
     * correspond au chauffeur et à la séquence — retourne la course fraîche
     * quand l'offre a bien été consommée, null sinon (rejeu/stale).
     */
    private function expirePendingOffer(
        string $companyId,
        int $rideId,
        int $driverId,
        int $seq,
        VtcRideEventType $eventType,
    ): ?VtcRide {
        return DB::transaction(function () use ($rideId, $driverId, $seq, $eventType): ?VtcRide {
            /** @var VtcRide|null $locked */
            $locked = VtcRide::query()->whereKey($rideId)->lockForUpdate()->first();

            if (! $locked instanceof VtcRide || $locked->status !== VtcRideStatus::Dispatching) {
                return null;
            }

            $pending = $this->pendingOffer($locked);

            if ($pending === null || $pending['driver_id'] !== $driverId || $pending['seq'] !== $seq) {
                return null;
            }

            $metadata = $locked->metadata ?? [];
            unset($metadata[self::PENDING_OFFER_KEY]);

            $locked->forceFill(['metadata' => $metadata])->save();

            $this->journal($locked, $eventType, [
                'driver_id' => $driverId,
                'seq' => $seq,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @return array{driver_id: int, seq: int, sent_at: string, expires_at: string}|null
     */
    private function pendingOffer(VtcRide $ride): ?array
    {
        $pending = ($ride->metadata ?? [])[self::PENDING_OFFER_KEY] ?? null;

        if (! is_array($pending)
            || ! is_numeric($pending['driver_id'] ?? null)
            || ! is_numeric($pending['seq'] ?? null)) {
            return null;
        }

        /** @var array{driver_id: int, seq: int, sent_at: string, expires_at: string} $normalized */
        $normalized = [
            'driver_id' => (int) $pending['driver_id'],
            'seq' => (int) $pending['seq'],
            'sent_at' => (string) ($pending['sent_at'] ?? ''),
            'expires_at' => (string) ($pending['expires_at'] ?? ''),
        ];

        return $normalized;
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

    private function radiusKm(): float
    {
        $radius = config('vtc.dispatch.radius_km', 5.0);
        $max = config('vtc.dispatch.max_radius_km', 25.0);

        $resolved = is_numeric($radius) ? (float) $radius : 5.0;
        $cap = is_numeric($max) ? (float) $max : 25.0;

        return max(0.1, min($cap, $resolved));
    }

    private function offerTimeoutSeconds(): int
    {
        $timeout = config('vtc.dispatch.offer_timeout_s', 30);

        return is_int($timeout) && $timeout > 0 ? $timeout : 30;
    }

    private function maxOffers(): int
    {
        $max = config('vtc.dispatch.max_offers', 5);

        return is_int($max) && $max > 0 ? $max : 5;
    }
}
