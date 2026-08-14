<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxSlabResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Events\TaxRateSubmittedForValidation;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class TaxSlabController extends Controller
{
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
            // Issue #1813 : la création passe par le workflow de validation —
            // statut `draft` par défaut (le platform admin doit approuver).
            // `active` reste accepté explicitement (rétrocompat / seeder API).
            'status' => ['sometimes', Rule::in([TaxSlab::STATUS_DRAFT, TaxSlab::STATUS_ACTIVE])],
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
            'status' => $validated['status'] ?? TaxSlab::STATUS_DRAFT,
        ]);

        // Audit trail immuable : création tracée (issue #1813).
        app(TaxRateValidationService::class)->recordCreated($slab, $actor);

        return (new TaxSlabResource($slab))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Issue #1813 — soumission pour validation (draft → pending_validation).
     * Rôle : manager (principal / comptable) du tenant de la ligne.
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
            $slab = app(TaxRateValidationService::class)->submit($taxSlab, $actor);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        TaxRateSubmittedForValidation::dispatch($slab->getTable(), (int) $slab->id, (int) $actor->id);

        return (new TaxSlabResource($slab))->response();
    }

    /**
     * Issue #1813 — historique immuable (tax_rate_change_log) d'un barème.
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
            'data' => app(TaxRateValidationService::class)->history($taxSlab)->map(fn ($entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'actor_id' => $entry->actor_id,
                'actor_role' => $entry->actor_role,
                'previous_value' => $entry->previous_value,
                'new_value' => $entry->new_value,
                'reason' => $entry->reason,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
        ]);
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

        $taxSlab->delete();

        return response()->json(['message' => 'Tax slab deleted successfully.']);
    }
}

