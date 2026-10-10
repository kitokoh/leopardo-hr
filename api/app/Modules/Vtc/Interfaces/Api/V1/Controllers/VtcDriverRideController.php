<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Application\Services\VtcDispatchService;
use App\Modules\Vtc\Application\Services\VtcDriverRideService;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcRideResource;
use Illuminate\Http\Request;

/**
 * Transitions de course côté chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 *   - accept / decline : réponse à l'offre COURANTE du dispatch (VTC-04 —
 *   verrou lockForUpdate, double acceptation impossible, déclinaison →
 *   candidat suivant immédiat) ;
 *   - arrive / start / complete : cycle de vie verrouillé par la state
 *   machine, journal append-only ; la clôture recalcule le prix final sur
 *   le trajet réel et libère le chauffeur (→ available).
 *
 * Périmètre : SES courses uniquement (findDriverRide, 404 uniforme).
 */
final class VtcDriverRideController extends VtcDriverBaseController
{
    public function __construct(
        private readonly VtcDispatchService $dispatch,
        private readonly VtcDriverRideService $driverRides,
    ) {}

    public function accept(Request $request, int $id): VtcRideResource
    {
        $driver = $this->resolveDriver($request);

        /** @var VtcRide $ride */
        $ride = VtcRide::query()->whereKey($id)->firstOrFail();

        return new VtcRideResource($this->dispatch->acceptOffer($ride, $driver->id));
    }

    public function decline(Request $request, int $id): VtcRideResource
    {
        $driver = $this->resolveDriver($request);

        /** @var VtcRide $ride */
        $ride = VtcRide::query()->whereKey($id)->firstOrFail();

        $this->dispatch->declineOffer($ride, $driver->id);

        return new VtcRideResource($ride->refresh());
    }

    public function arrive(Request $request, int $id): VtcRideResource
    {
        $driver = $this->resolveDriver($request);

        return new VtcRideResource(
            $this->driverRides->arrive($this->findDriverRide($driver, $id))
        );
    }

    public function start(Request $request, int $id): VtcRideResource
    {
        $driver = $this->resolveDriver($request);

        return new VtcRideResource(
            $this->driverRides->start($this->findDriverRide($driver, $id))
        );
    }

    public function complete(Request $request, int $id): VtcRideResource
    {
        $driver = $this->resolveDriver($request);

        return new VtcRideResource(
            $this->driverRides->complete($this->findDriverRide($driver, $id))
        );
    }
}
