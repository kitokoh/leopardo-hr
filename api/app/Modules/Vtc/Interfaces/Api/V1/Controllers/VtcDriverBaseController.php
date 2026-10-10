<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Http\Request;

/**
 * Base des surfaces chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 * Résout la fiche chauffeur de l'employé authentifié (`user_id` — deny-by-
 * default : aucun accès « chauffeur » sans fiche rattachée, 403
 * VTC_DRIVER_NOT_LINKED) et borne le périmètre : un chauffeur ne voit QUE
 * ses offres et ses courses (404 uniforme, jamais un 403 qui révélerait
 * l'existence d'une ressource d'un autre chauffeur).
 */
abstract class VtcDriverBaseController
{
    /**
     * Fiche chauffeur de l'employé courant — 403 fail-closed si absente.
     */
    protected function resolveDriver(Request $request): VtcDriver
    {
        /** @var VtcDriver|null $driver */
        $driver = VtcDriver::query()
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->first();

        if (! $driver instanceof VtcDriver) {
            abort(response()->json([
                'error' => 'VTC_DRIVER_NOT_LINKED',
                'message' => 'No VTC driver profile is linked to this account.',
            ], 403));
        }

        return $driver;
    }

    /**
     * Course assignée à CE chauffeur — 404 uniforme sinon (fail-closed).
     */
    protected function findDriverRide(VtcDriver $driver, int $rideId): VtcRide
    {
        /** @var VtcRide|null $ride */
        $ride = VtcRide::query()->whereKey($rideId)->first();

        if (! $ride instanceof VtcRide || $ride->driver_id !== $driver->id) {
            abort(404);
        }

        return $ride;
    }
}
