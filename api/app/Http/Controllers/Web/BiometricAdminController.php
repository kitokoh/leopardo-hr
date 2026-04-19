<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\BiometricEnrollmentRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Services\BiometricEnrollmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BiometricAdminController extends Controller
{
    public function __construct(
        private readonly BiometricEnrollmentService $biometricEnrollmentService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->setTenantSearchPath($actor->company);

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
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->setTenantSearchPath($actor->company);

        $validated = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = BiometricEnrollmentRequest::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($id);

        $this->biometricEnrollmentService->approve($actor, $item, $validated['manager_note'] ?? null);

        return redirect()->route('biometrics.index')->with('status', 'Demande biometrie approuvee.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->setTenantSearchPath($actor->company);

        $validated = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = BiometricEnrollmentRequest::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($id);

        $this->biometricEnrollmentService->reject($actor, $item, $validated['manager_note'] ?? null);

        return redirect()->route('biometrics.index')->with('status', 'Demande biometrie rejetee.');
    }

    public function createKiosk(Request $request): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->setTenantSearchPath($actor->company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
        ]);

        $plainToken = Str::random(48);

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $actor->company_id,
            'name' => $validated['name'],
            'location_label' => $validated['location_label'] ?? null,
            'biometric_mode' => $validated['biometric_mode'] ?? 'fingerprint',
            'device_code' => strtoupper(Str::random(10)),
            'sync_token_hash' => Hash::make($plainToken),
            'status' => 'active',
        ]);

        return redirect()
            ->route('biometrics.index')
            ->with('status', 'Borne d entree creee.')
            ->with('kiosk_credentials', [
                'device_code' => $kiosk->device_code,
                'sync_token' => $plainToken,
            ]);
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
