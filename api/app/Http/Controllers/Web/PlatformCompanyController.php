<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Services\CompanyProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformCompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $companyProvisioningService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        $companies = Company::query()->latest()->limit(50)->get();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => $companies,
            ]);
        }

        return view('platform.companies.index', [
            'companies' => $companies,
        ]);
    }

    public function create(): View
    {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        return view('platform.companies.create', [
            'plans' => DB::table('plans')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('companies', 'slug')],
            'sector' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique('companies', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'language' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'manager_first_name' => ['required', 'string', 'max:100'],
            'manager_last_name' => ['required', 'string', 'max:100'],
            'manager_email' => ['required', 'email', 'max:150'],
            'manager_phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (DB::getDriverName() === 'pgsql' && DB::table('public.user_lookups')->where('email', $validated['manager_email'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['manager_email' => 'Cet email est deja utilise par un utilisateur existant.']);
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');

        $result = $this->companyProvisioningService->provisionSharedCompany($validated, $superAdmin);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => [
                    'company' => $result['company'],
                    'manager' => [
                        'id' => $result['manager']->id,
                        'email' => $result['manager']->email,
                        'role' => $result['manager']->role,
                        'manager_role' => $result['manager']->manager_role,
                    ],
                ],
            ], 201);
        }

        return redirect()
            ->route('platform.companies.index')
            ->with('status', 'Societe creee et invitation manager envoyee.');
    }

    /**
     * Ecran d edition d une societe : toggler les modules actifs, changer
     * le statut, les notes et le plan. Entree principale du super-admin pour
     * repondre a "un client demande le module Securite / Muhasebe / Finance".
     */
    public function edit(string $companyId): View
    {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        $company = Company::query()->findOrFail($companyId);

        return view('platform.companies.edit', [
            'company' => $company,
            'plans' => DB::table('plans')->orderBy('id')->get(),
            'known_modules' => Company::KNOWN_MODULES,
        ]);
    }

    /**
     * Mise a jour des attributs editables par le super-admin :
     *   - features (toggle par module connu)
     *   - status (active / suspended / expired)
     *   - notes / plan_id
     * On ne touche ni a schema_name, ni a tenancy_type, ni aux identifiants
     * structurels (email societe, slug) qui sont figes apres provisioning.
     */
    public function update(Request $request, string $companyId): RedirectResponse|JsonResponse
    {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        $company = Company::query()->findOrFail($companyId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
        ]);

        $company->status = $validated['status'];
        $company->plan_id = $validated['plan_id'];
        $company->notes = $validated['notes'] ?? null;

        // On reconstruit la map features uniquement a partir des modules connus
        // (Company::KNOWN_MODULES). Un toggle absent = false, sauf rh qui reste
        // active par defaut (base de l app, APV L.08).
        $submitted = $validated['features'] ?? [];
        $features = [];
        foreach (Company::KNOWN_MODULES as $module) {
            if ($module === 'rh') {
                $features['rh'] = true;
                continue;
            }
            $features[$module] = (bool) ($submitted[$module] ?? false);
        }
        $company->features = $features;
        $company->save();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => [
                    'company' => $company->fresh(),
                ],
            ]);
        }

        return redirect()
            ->route('platform.companies.edit', ['company' => $company->id])
            ->with('status', 'Societe mise a jour.');
    }

    /**
     * Renvoie l invitation du manager principal de la societe.
     * Utile quand l email initial n est jamais arrive ou que le lien a expire.
     * Passe par createAndSend qui fait un updateOrCreate sur (company_id,
     * employee_id) et invalide automatiquement l ancien token.
     */
    public function resendManagerInvitation(
        Request $request,
        string $companyId,
        \App\Services\UserInvitationService $invitationService,
    ): RedirectResponse {
        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));

        $company = Company::query()->findOrFail($companyId);

        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('shared_tenants'));
        $managerEmployee = \App\Models\Employee::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('role', 'manager')
            ->where('manager_role', 'principal')
            ->first();

        if ($managerEmployee === null) {
            DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));
            return back()->withErrors(['resend' => 'Aucun manager principal trouve pour cette societe.']);
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');

        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('public'));
        $invitationService->createAndSend(
            company: $company,
            employee: $managerEmployee,
            invitedByType: 'super_admin',
            invitedByEmail: $superAdmin->email,
        );

        return back()->with('status', 'Invitation manager renvoyee.');
    }
}
