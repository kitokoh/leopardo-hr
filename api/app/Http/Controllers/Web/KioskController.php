<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Services\KioskAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KioskController extends Controller
{
    /**
     * Cle de session utilisee pour lier une session navigateur a un kiosque deja
     * appaire avec son sync_token. La valeur est le hash bcrypt du token associe
     * au device_code : on ne conserve jamais le token en clair cote session.
     */
    private const SESSION_KIOSK_KEY = 'kiosk.pairing.token_hash';

    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {}

    public function show(Request $request, string $deviceCode): View|RedirectResponse
    {
        DB::statement('SET search_path TO shared_tenants,public');

        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        // Pairing initial : la borne doit etre ouverte une premiere fois avec
        // le sync_token fourni au manager lors de la creation du kiosque.
        // Apres verification, on memorise le hash du token en session pour ne
        // plus avoir a le repasser a chaque pointage.
        $providedToken = (string) $request->query('token', '');
        if ($providedToken !== '' && Hash::check($providedToken, (string) $kiosk->sync_token_hash)) {
            $request->session()->put(self::SESSION_KIOSK_KEY.'.'.$kiosk->device_code, $kiosk->sync_token_hash);

            return redirect()->route('kiosk.show', $kiosk->device_code);
        }

        abort_unless(
            $this->sessionMatchesKiosk($request, $kiosk),
            401,
            'KIOSK_NOT_PAIRED'
        );

        $this->setTenantSearchPath($kiosk->company);

        return view('kiosk.show', [
            'kiosk' => $kiosk,
            'company' => $kiosk->company,
        ]);
    }

    public function punch(Request $request, string $deviceCode): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless(
            $this->sessionMatchesKiosk($request, $kiosk),
            401,
            'KIOSK_NOT_PAIRED'
        );

        app()->instance('current_company', $kiosk->company);
        $this->setTenantSearchPath($kiosk->company);

        $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
        );

        return redirect()
            ->route('kiosk.show', $kiosk->device_code)
            ->with('status', 'Pointage enregistre avec succes.');
    }

    private function sessionMatchesKiosk(Request $request, AttendanceKiosk $kiosk): bool
    {
        $sessionHash = (string) $request->session()->get(self::SESSION_KIOSK_KEY.'.'.$kiosk->device_code, '');

        return $sessionHash !== '' && hash_equals((string) $kiosk->sync_token_hash, $sessionHash);
    }

    private function setTenantSearchPath(?Company $company): void
    {
        if (! $company) {
            DB::statement('SET search_path TO shared_tenants,public');

            return;
        }

        if ($company->tenancy_type === 'schema' && $company->schema_name) {
            DB::statement('SET search_path TO '.$company->schema_name.',public');

            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
    }
}
