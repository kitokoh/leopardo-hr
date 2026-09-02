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
 *   - le kiosque résolu (relation `company` chargée) est posé en attribut
 *     `kiosk_device` ;
 *   - le search_path PostgreSQL est restauré AVANT `$next()` (pattern
 *     #2689/#2973/#3368) : le contrôleur en aval retrouve le search_path
 *     d'origine — jamais `public` (PlatformCompanyLookup bascule dessus) —
 *     et les services ouvrent leur propre contexte tenant (withinTenant).
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

        try {
            if ($deviceCode === '') {
                abort(401, 'INVALID_KIOSK_TOKEN');
            }

            DB::statement('SET search_path TO shared_tenants,public');

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

            $token = (string) $request->header('X-Kiosk-Token', '');
            if ($token === '' || ! Hash::check($token, (string) $kiosk->sync_token_hash)) {
                Log::channel('audit')->warning('kiosk_auth.failed', [
                    'device_code' => $deviceCode,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'INVALID_KIOSK_TOKEN');
            }

            if ($kiosk->company_id !== null) {
                // PlatformCompanyLookup bascule sur `public` pour lire
                // companies : on rétablit aussitôt le contexte kiosque.
                $kiosk->setRelation('company', PlatformCompanyLookup::findOrFail((string) $kiosk->company_id));
                DB::statement('SET search_path TO shared_tenants,public');
            }

            $request->attributes->set('kiosk_device', $kiosk);
        } finally {
            // Restauré AVANT $next : le contrôleur et les services ouvrent
            // leur propre contexte tenant (TenantManager::withinTenant).
            DB::statement('SET search_path TO '.$previous);
        }

        return $next($request);
    }
}
