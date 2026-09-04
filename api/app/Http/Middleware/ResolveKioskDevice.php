<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Support\PlatformCompanyLookup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ATT-004 (#6769) / BIO-005 (#6766) — authentification appareil kiosque
 * (X-Kiosk-Token) centralisée pour les routes kiosque versionnées.
 *
 * Même sémantique que `KioskController::resolveAuthorizedKiosk()` :
 *   - lookup par hash déterministe du device_code (#5588) ;
 *   - appareil révoqué → 403 DEVICE_REVOKED (jamais un 404 ambigu) ;
 *   - token invalide → 401 INVALID_KIOSK_TOKEN (audité canal `audit`) ;
 *   - le search_path PostgreSQL est restauré dans tous les cas (try/finally,
 *     pattern #2689/#3368) ;
 *   - le kiosque résolu (relation `company` chargée) est posé en attribut
 *     `kiosk_device` pour les contrôleurs.
 */
final class ResolveKioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceCode = (string) $request->route('deviceCode', '');
        $previous = 'public,shared_tenants';

        try {
            $searchPathRow = DB::selectOne('SHOW search_path');
            if (is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')) {
                $previous = (string) $searchPathRow->search_path;
            }
        } catch (Throwable) {
            // défaut conservé
        }

        DB::statement('SET search_path TO shared_tenants,public');

        try {
            if ($deviceCode === '') {
                abort(401, 'INVALID_KIOSK_TOKEN');
            }

            $kiosk = AttendanceKiosk::query()
                ->where('device_code', AttendanceKiosk::hashDeviceCode($deviceCode))
                ->first();

            if (! $kiosk) {
                Log::channel('audit')->warning('kiosk_auth.unknown_device', [
                    'device_code' => $deviceCode,
                    'ip' => $request->ip(),
                ]);

                abort(401, 'INVALID_KIOSK_TOKEN');
            }

            if ($kiosk->isRevoked()) {
                Log::channel('audit')->warning('kiosk_auth.revoked_device', [
                    'device_code' => $deviceCode,
                    'company_id' => $kiosk->company_id,
                    'ip' => $request->ip(),
                ]);

                abort(403, 'DEVICE_REVOKED');
            }

            if ($kiosk->company_id !== null) {
                $kiosk->setRelation('company', PlatformCompanyLookup::findOrFail((string) $kiosk->company_id));
            }

            $token = (string) $request->header('X-Kiosk-Token', '');
            if ($token === '' || ! Hash::check($token, (string) $kiosk->sync_token_hash)) {
                Log::channel('audit')->warning('kiosk_auth.failed', [
                    'device_code' => $deviceCode,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'INVALID_KIOSK_TOKEN');
            }

            $request->attributes->set('kiosk_device', $kiosk);

            return $next($request);
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }
}
