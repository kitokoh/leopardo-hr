<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskAttendanceService;
use App\Support\PlatformCompanyLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {}

    public function show(Request $request, string $deviceCode): View
    {
        DB::statement('SET search_path TO shared_tenants,public');

        // Issue #5588 : lookup par hash déterministe (device_code non stocké
        // en clair — AttendanceKiosk::hashDeviceCode).
        $kiosk = AttendanceKiosk::query()
            ->where('device_code', AttendanceKiosk::hashDeviceCode($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        // #4172 : la page web kiosk est publique (URL = device_code, borne
        // terrain) mais le POST /punch ne doit être accepté que depuis une
        // session qui a réellement chargé la page : un script qui possède
        // uniquement le device_code (logs, QR, provisioning) ne peut plus
        // forger des pointages en masse.
        $request->session()->put(self::sessionKey($kiosk), true);

        // Le modèle AttendanceKiosk ne définit pas de relation company() :
        // résolution explicite via le lookup plateforme (même pattern que
        // l'API kiosk, KioskController::sync). Sans cela, $kiosk->company
        // vaut null et la vue crash sur $company->name.
        $company = PlatformCompanyLookup::findOrFail((string) $kiosk->company_id);
        $kiosk->setRelation('company', $company);

        $this->setTenantSearchPath($company);

        return view('kiosk.show', [
            'kiosk' => $kiosk,
            'company' => $company,
        ]);
    }

    public function punch(Request $request, string $deviceCode): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        // Issue #5588 : lookup par hash déterministe (device_code non stocké
        // en clair — AttendanceKiosk::hashDeviceCode).
        $kiosk = AttendanceKiosk::query()
            ->where('device_code', AttendanceKiosk::hashDeviceCode($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        // #4172 : défense en profondeur — le punch web exige une session qui a
        // chargé la page kiosk (le device_code seul ne suffit plus à forger
        // des pointages). Les échecs sont tracés sur le canal audit, comme
        // pour l'API kiosk (PA2-API-005).
        if (! $request->session()->get(self::sessionKey($kiosk))) {
            Log::channel('audit')->warning('kiosk_web_punch.denied', [
                'device_code' => strtoupper($deviceCode),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // #4812 : littéral EN déplacé au catalogue errors.*
            abort(403, __('errors.KIOSK_SESSION_REQUIRED'));
        }

        $company = PlatformCompanyLookup::findOrFail((string) $kiosk->company_id);
        $kiosk->setRelation('company', $company);
        app()->instance('current_company', $company);
        $this->setTenantSearchPath($company);

        $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
        );

        return redirect()
            // Issue #5588 : le device_code stocké est un hash — on redirige
            // avec le code EN CLAIR reçu dans l'URL (celui du kiosque).
            ->route('kiosk.show', $deviceCode)
            ->with('status', 'Pointage enregistre avec succes.');
    }

    private static function sessionKey(AttendanceKiosk $kiosk): string
    {
        return 'kiosk_punch_'.strtolower((string) $kiosk->id);
    }

    private function setTenantSearchPath(?Company $company): void
    {
        if (! $company) {
            DB::statement('SET search_path TO shared_tenants,public');

            return;
        }

        if ($company->tenancy_type === 'schema' && $company->schema_name) {
            DB::statement('SET search_path TO '.$company->getSafeSearchPath());

            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
    }
}
