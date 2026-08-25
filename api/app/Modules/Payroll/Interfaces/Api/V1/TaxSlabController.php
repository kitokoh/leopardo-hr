<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxSlabResource;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use App\Rules\SupportedCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TaxSlabController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $validation) {}

    public function index(Request $request): JsonResponse
    {
        // Issue #1917 : autorisation via Policy Laravel (manager/super-admin).
        $this->authorize('viewAny', TaxSlab::class);

        /** @var Employee $actor */
        $actor = $request->user();

        // Scope to the authenticated employee's company for tenant isolation.
        // Without this filter, slabs from other tenants leak across companies.
        $query = TaxSlab::where('company_id', $actor->company_id);

        if ($request->filled('country_code')) {
            $query->forCountry($request->input('country_code'));
        }

        return TaxSlabResource::collection($query->orderBy('country_code')->orderBy('min_amount')->get())->response();
    }

    public function store(Request $request): JsonResponse
    {
        // Issue #1917 : Policy Laravel.
        $this->authorize('create', TaxSlab::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2', new SupportedCountry], // #1951 contrat partagé
            'name' => 'required|string|max:150',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'required|numeric|min:0|max:100',
            'fixed_deduction' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $slab = TaxSlab::create([
            'company_id' => $actor->company_id,
            'country_code' => $validated['country_code'],
            'name' => $validated['name'],
            'min_amount' => $validated['min_amount'],
            'max_amount' => $validated['max_amount'] ?? null,
            'rate' => $validated['rate'],
            'fixed_deduction' => $validated['fixed_deduction'] ?? 0,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            // Issue #1813 : toute création passe par le workflow de validation
            // (status = draft ; l'approbation d'un platform_admin est requise
            // pour que la ligne soit utilisée dans les calculs).
            'status' => TaxSlab::STATUS_DRAFT,
        ]);

        $this->validation->logCreated($slab, $actor);

        return (new TaxSlabResource($slab))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        // Contrat isolation (PayrollTenantIsolationTest) : une ressource
        // d'un AUTRE tenant répond 404 (pas 403 — pas de fuite d'existence)
        // avant la Policy (rôle).
        /** @var Employee $actor */
        $actor = $request->user();

        if ($taxSlab->company_id !== $actor->company_id) {
            abort(404);
        }

        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('update', $taxSlab);

        // Issue #1813 : une ligne soumise/active ne se modifie plus directement —
        // on propose une nouvelle modification (draft) via le workflow.
        if ($taxSlab->status !== TaxSlab::STATUS_DRAFT) {
            abort(409, __('payroll.rate_edit_locked'));
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'fixed_deduction' => 'sometimes|numeric|min:0',
            'effective_from' => 'sometimes|date',
            // S-3 (#1663) : effective_to doit rester strictement postérieur à
            // effective_from même en update partiel (Rule::when garde le cas
            // où effective_from n'est pas fourni dans la requête).
            'effective_to' => [
                'nullable',
                'date',
                Rule::when($request->filled('effective_from'), 'after:effective_from'),
            ],
        ]);

        $taxSlab->update($validated);

        return (new TaxSlabResource($taxSlab->refresh()))->response();
    }

    public function destroy(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        // Contrat isolation : 404 cross-tenant avant la Policy (voir update).
        // @var Employee : le middleware tenant garantit un employé authentifié.
        /** @var Employee $actor */
        $actor = $request->user();

        if ($taxSlab->company_id !== $actor->company_id) {
            abort(404);
        }

        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('delete', $taxSlab);

        // Issue #1813 : seules les lignes draft peuvent être supprimées.
        if ($taxSlab->status !== TaxSlab::STATUS_DRAFT) {
            abort(409, __('payroll.rate_delete_draft_only'));
        }

        $taxSlab->delete();

        return response()->json(['message' => __('payroll.tax_slab_deleted')]);
    }

    /**
     * Soumet une ligne draft pour validation par un platform_admin.
     */
    public function submit(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('submit', $taxSlab);

        /** @var Employee $actor */
        $actor = $request->user();

        try {
            $this->validation->submit($taxSlab, $actor);
        } catch (\DomainException $e) {
            // #3810 : code stable — le message brut (règle métier interne) reste en logs.
            Log::error('tax_slab.submit_failed', ['slab_id' => $taxSlab->id, 'error' => $e->getMessage()]);
            abort(422, __('errors.TAX_SLAB_SUBMIT_FAILED'));
        }

        return (new TaxSlabResource($taxSlab->refresh()))->response();
    }

    /**
     * Historique immuable des modifications d'une ligne (audit trail).
     */
    public function history(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('view', $taxSlab);

        return response()->json([
            'data' => $this->validation->history($taxSlab)
                ->map(fn (TaxRateChangeLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor_id' => $log->actor_id,
                    'actor_role' => $log->actor_role,
                    'previous_value' => $log->previous_value,
                    'new_value' => $log->new_value,
                    'reason' => $log->reason,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
        ]);
    }
}
