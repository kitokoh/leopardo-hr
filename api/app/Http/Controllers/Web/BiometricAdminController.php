<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Attendance\Infrastructure\Services\BiometricEnrollmentService;
use App\Support\SearchPathGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BiometricAdminController extends Controller
{
    public function __construct(
        private readonly BiometricEnrollmentService $biometricEnrollmentService,
    ) {}

    public function index(Request $request): View
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // Issue #9574 : le `SET search_path` etait pose sans restauration. Sur
        // une connexion reutilisee (PHP-FPM, worker), la session restait sur le
        // schema du tenant et les requetes suivantes echouaient en 42P01.
        return SearchPathGuard::run(self::tenantSearchPath($actor->company), function () use ($actor): View {
            $requests = BiometricEnrollmentRequest::query()
                ->where('company_id', $actor->company_id)
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByDesc('submitted_at')
                ->limit(50)
                ->get();

            $kiosks = AttendanceKiosk::query()
                ->where('company_id', $actor->company_id)
                ->latest('id')
                ->get();

            return view('biometrics.index', [
                'requests' => $requests,
                'kiosks' => $kiosks,
            ]);
        });
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return SearchPathGuard::run(self::tenantSearchPath($actor->company), function () use ($request, $actor, $id): RedirectResponse {
            $validated = $request->validate([
                'manager_note' => ['nullable', 'string', 'max:1000'],
            ]);

            $item = BiometricEnrollmentRequest::query()
                ->where('company_id', $actor->company_id)
                ->findOrFail($id);

            $this->biometricEnrollmentService->approve($actor, $item, $validated['manager_note'] ?? null);

            return redirect()->route('biometrics.index')->with('status', 'Demande biometrie approuvee.');
        });
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return SearchPathGuard::run(self::tenantSearchPath($actor->company), function () use ($request, $actor, $id): RedirectResponse {
            $validated = $request->validate([
                'manager_note' => ['nullable', 'string', 'max:1000'],
            ]);

            $item = BiometricEnrollmentRequest::query()
                ->where('company_id', $actor->company_id)
                ->findOrFail($id);

            $this->biometricEnrollmentService->reject($actor, $item, $validated['manager_note'] ?? null);

            return redirect()->route('biometrics.index')->with('status', 'Demande biometrie rejetee.');
        });
    }

    public function createKiosk(Request $request): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return SearchPathGuard::run(self::tenantSearchPath($actor->company), function () use ($request, $actor): RedirectResponse {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'location_label' => ['nullable', 'string', 'max:120'],
                'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
            ]);

            $plainToken = Str::random(48);
            $plainDeviceCode = strtoupper(Str::random(10));

            $kiosk = AttendanceKiosk::query()->create([
                'company_id' => $actor->company_id,
                'name' => $validated['name'],
                'location_label' => $validated['location_label'] ?? null,
                'biometric_mode' => $validated['biometric_mode'] ?? 'fingerprint',
                // Issue #5588 : device_code haché au repos (lookup déterministe),
                // code en clair retourné une seule fois au provisioning.
                'device_code' => AttendanceKiosk::hashDeviceCode($plainDeviceCode),
                'sync_token_hash' => Hash::make($plainToken),
                'status' => 'active',
            ]);

            return redirect()
                ->route('biometrics.index')
                ->with('status', 'Borne d entree creee.')
                ->with('kiosk_credentials', [
                    'device_code' => $plainDeviceCode,
                    'sync_token' => $plainToken,
                ]);
        });
    }

    /**
     * Chemin de recherche du tenant de l'appelant.
     *
     * La méthode ne pose plus rien elle-même : `SearchPathGuard::run()` encadre
     * la pose et la restauration (issue #9574).
     */
    private static function tenantSearchPath(?Company $company): string
    {
        if ($company instanceof Company
            && $company->tenancy_type === 'schema'
            && $company->schema_name) {
            return $company->getSafeSearchPath();
        }

        return 'shared_tenants,public';
    }
}
