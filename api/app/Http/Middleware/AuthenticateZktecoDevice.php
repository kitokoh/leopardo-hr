<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * #4934 (audit web client 2026-08-17) — formalise l'authentification des
 * devices ZKTeco (heartbeat / sync-attendance) en middleware dédié, au lieu
 * d'une méthode privée du contrôleur.
 *
 * Comportement (repris à l'identique de #2216 / #4787) :
 *  - résolution du device par `serial_number` (param de route) ;
 *  - vérification du header `X-Device-Token` contre `sync_token_hash`
 *    (hashé au repos) ; fail-closed : device sans token → 401
 *    `DEVICE_TOKEN_NOT_SET`, token invalide → 401 `INVALID_DEVICE_TOKEN` ;
 *  - échecs journalisés sur le canal 'audit' (IP + user agent) ;
 *  - search_path PostgreSQL restauré en `finally` (pattern #4787) : le
 *    lookup ne doit jamais être cross-tenant sur un worker persistant.
 *
 * Le device authentifié est exposé via
 * `$request->attributes->get('zkteco_device')`.
 */
class AuthenticateZktecoDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $serialNumber = (string) $request->route('serialNumber', '');

        // #4787 : lecture du search_path courant (variante nullsafe refusée
        // par PHPStan strict — garde is_object + property_exists, cf. #2973).
        $previous = 'public,shared_tenants';
        try {
            $searchPathRow = DB::selectOne('SHOW search_path');
            if (is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')) {
                $previous = (string) $searchPathRow->search_path;
            }
        } catch (\Throwable) {
            // défaut conservé
        }
        DB::statement('SET search_path TO shared_tenants,public');

        try {
            $device = ZktecoDevice::query()
                ->where('serial_number', $serialNumber)
                ->firstOrFail();

            $token = (string) $request->header('X-Device-Token', '');

            if (empty($device->sync_token_hash)) {
                Log::channel('audit')->warning('zkteco_auth.not_configured', [
                    'serial_number' => $serialNumber,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'DEVICE_TOKEN_NOT_SET');
            }

            if ($token === '' || ! Hash::check($token, (string) $device->sync_token_hash)) {
                Log::channel('audit')->warning('zkteco_auth.failed', [
                    'serial_number' => $serialNumber,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'INVALID_DEVICE_TOKEN');
            }

            $request->attributes->set('zkteco_device', $device);

            return $next($request);
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }
}
