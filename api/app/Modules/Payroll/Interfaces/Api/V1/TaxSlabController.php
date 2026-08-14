<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxSlabResource;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxSlabController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $validation) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN,CM,CF,TD,CG,GA,GQ,CI,ML,BF,BJ,TG,NE,CA',
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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        // Tenant isolation: reject cross-company access.
        if ((string) $taxSlab->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        // Issue #1813 : une ligne soumise/active ne se modifie plus directement —
        // on propose une nouvelle modification (draft) via le workflow.
        if ($taxSlab->status !== TaxSlab::STATUS_DRAFT) {
            abort(409, 'Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.');
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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        // Tenant isolation: reject cross-company access.
        if ((string) $taxSlab->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        // Issue #1813 : seules les lignes draft peuvent être supprimées.
        if ($taxSlab->status !== TaxSlab::STATUS_DRAFT) {
            abort(409, 'Seule une ligne en brouillon peut être supprimée.');
        }

        $taxSlab->delete();

        return response()->json(['message' => 'Tax slab deleted successfully.']);
    }

    /**
     * Soumet une ligne draft pour validation par un platform_admin.
     */
    public function submit(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        if ((string) $taxSlab->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        try {
            $this->validation->submit($taxSlab, $actor);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return (new TaxSlabResource($taxSlab->refresh()))->response();
    }

    /**
     * Historique immuable des modifications d'une ligne (audit trail).
     */
    public function history(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        if ((string) $taxSlab->company_id !== (string) $actor->company_id) {
            abort(404);
        }

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
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
