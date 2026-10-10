<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Services;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideCompleted;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcDriverPosition;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\Support\VtcFareCalculator;
use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Support\Facades\DB;

/**
 * Transitions de course portées par le chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 *   - arrive   : accepted → arrived (chauffeur sur place) ;
 *   - start    : arrived → in_progress (course démarrée) ;
 *   - complete : in_progress → completed — prix final recalculé sur le
 *     TRAJET RÉEL (somme des distances entre positions ingérées pendant la
 *     course via le core geo BC-33, × road_factor, durée réelle) ; à moins
 *     de deux positions ingérées, l'estimation devient le prix final
 *     (dégradation honnête, spec §5.4). Le chauffeur redevient `available`.
 *
 * Chaque transition : verrou lockForUpdate, state machine, journal
 * append-only — le périmètre (course assignée à CE chauffeur) est vérifié
 * en amont par le contrôleur (404 uniforme).
 */
final class VtcDriverRideService
{
    public function __construct(
        private readonly GeoServiceContract $geo,
        private readonly VtcRideStateMachine $stateMachine,
        private readonly VtcFareCalculator $fareCalculator,
    ) {}

    /**
     * @throws InvalidRideTransitionException
     */
    public function arrive(VtcRide $ride): VtcRide
    {
        return $this->transition($ride, VtcRideStatus::Arrived, 'arrived_at', VtcRideEventType::DriverArrived);
    }

    /**
     * @throws InvalidRideTransitionException
     */
    public function start(VtcRide $ride): VtcRide
    {
        return $this->transition($ride, VtcRideStatus::InProgress, 'started_at', VtcRideEventType::RideStarted);
    }

    /**
     * Clôture : prix final recalculé sur le trajet réel, chauffeur libéré,
     * événement VtcRideCompleted (intégration billing future, spec §11).
     *
     * @throws InvalidRideTransitionException
     */
    public function complete(VtcRide $ride): VtcRide
    {
        $completed = DB::transaction(function () use ($ride): VtcRide {
            /** @var VtcRide $locked */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransitionTo($locked->status, VtcRideStatus::Completed);

            $completedAt = now();
            $finalPriceMinor = $this->computeFinalPrice($locked, $completedAt);

            $locked->forceFill([
                'status' => VtcRideStatus::Completed->value,
                'completed_at' => $completedAt,
                'final_price_minor' => $finalPriceMinor,
            ])->save();

            // Le chauffeur redevient disponible pour le dispatch.
            if ($locked->driver_id !== null) {
                VtcDriver::query()
                    ->whereKey($locked->driver_id)
                    ->update(['status' => VtcDriverStatus::Available->value]);
            }

            VtcRideEvent::create([
                'ride_id' => $locked->id,
                'type' => VtcRideEventType::RideCompleted->value,
                'payload' => [
                    'driver_id' => $locked->driver_id,
                    'final_price_minor' => $finalPriceMinor,
                    'estimated_price_minor' => $locked->estimated_price_minor,
                ],
            ]);

            return $locked;
        });

        if ($completed->driver_id !== null) {
            VtcRideCompleted::dispatch(
                (string) $completed->company_id,
                $completed->id,
                $completed->reference,
                $completed->driver_id,
                $completed->final_price_minor,
                $completed->currency,
            );
        }

        return $completed->refresh();
    }

    /**
     * Transition simple (arrive/start) : verrou + state machine + horodatage
     * + journal — le périmètre chauffeur est vérifié par le contrôleur.
     *
     * @throws InvalidRideTransitionException
     */
    private function transition(
        VtcRide $ride,
        VtcRideStatus $to,
        string $timestampColumn,
        VtcRideEventType $eventType,
    ): VtcRide {
        return DB::transaction(function () use ($ride, $to, $timestampColumn, $eventType): VtcRide {
            /** @var VtcRide $locked */
            $locked = VtcRide::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->assertCanTransitionTo($locked->status, $to);

            $locked->forceFill([
                'status' => $to->value,
                $timestampColumn => now(),
            ])->save();

            VtcRideEvent::create([
                'ride_id' => $locked->id,
                'type' => $eventType->value,
                'payload' => ['driver_id' => $locked->driver_id],
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Prix final (spec §5.4) : distance réelle = somme des distances entre
     * positions consécutives ingérées depuis le démarrage (core geo, jamais
     * de calcul local), × road_factor, durée réelle — formule tarifaire
     * identique à l'estimation. Repli : prix estimé si trajet inexploitable.
     */
    private function computeFinalPrice(VtcRide $ride, \Illuminate\Support\Carbon $completedAt): ?int
    {
        $profile = $ride->fareProfile;

        if ($profile === null || $ride->driver_id === null || $ride->started_at === null) {
            return $ride->estimated_price_minor;
        }

        /** @var list<VtcDriverPosition> $positions */
        $positions = VtcDriverPosition::query()
            ->where('driver_id', $ride->driver_id)
            ->where('recorded_at', '>=', $ride->started_at)
            ->where('recorded_at', '<=', $completedAt)
            ->orderBy('recorded_at')
            ->get()
            ->all();

        if (count($positions) < 2) {
            return $ride->estimated_price_minor;
        }

        $actualMeters = 0;

        for ($i = 1; $i < count($positions); $i++) {
            $actualMeters += $this->geo->distanceMeters(
                new GeoPoint($positions[$i - 1]->latitude, $positions[$i - 1]->longitude),
                new GeoPoint($positions[$i]->latitude, $positions[$i]->longitude),
            );
        }

        $roadDistanceMeters = (int) round($actualMeters * $this->roadFactor());
        $durationSeconds = max(0, (int) $ride->started_at->diffInSeconds($completedAt));

        return $this->fareCalculator->priceMinor($profile, $roadDistanceMeters, $durationSeconds);
    }

    private function roadFactor(): float
    {
        $factor = config('vtc.pricing.road_factor', 1.3);

        return is_numeric($factor) ? max(1.0, (float) $factor) : 1.3;
    }
}
