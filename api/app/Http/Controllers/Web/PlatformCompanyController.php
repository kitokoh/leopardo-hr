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
use Illuminate\Validation\Rule;

class PlatformCompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $companyProvisioningService,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        DB::statement('SET search_path TO public');

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
        DB::statement('SET search_path TO public');

        return view('platform.companies.create', [
            'plans' => DB::table('plans')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        DB::statement('SET search_path TO public');

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

}
