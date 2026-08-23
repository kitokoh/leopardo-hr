<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use App\Rules\SupportedCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SocialContributionController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $validation) {}

    public function index(Request $request): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager/super-admin).
        $this->authorize('viewAny', SocialContribution::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $query = SocialContribution::where('company_id', $actor->company_id);

        if ($request->filled('country_code')) {
            $query->forCountry($request->input('country_code'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return SocialContributionResource::collection($query->orderBy('country_code')->orderBy('type')->orderBy('name')->get())->response();
    }

    public function store(Request $request): JsonResponse
    {
        // Issue #1917 : Policy Laravel.
        $this->authorize('create', SocialContribution::class);

        /** @var Employee $actor */
        $actor = $request->user();

        // PA2-ARCH-004: uniqueness is scoped to (company_id, code,
        // effective_from) rather than a bare global code, so a new dated
        // rate for an existing contribution code can be added without
        // deleting the historical row a past payroll run needs to be
        // recalculated against for an audit. See the
        // make_social_contributions_code_unique_per_effective_period
        // migration.
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2', new SupportedCountry], // #1951 contrat partagé
            'name' => 'required|string|max:150',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('social_contributions', 'code')
                    ->where('company_id', $actor->company_id)
                    ->where('effective_from', $request->string('effective_from')->value()),
            ],
            'type' => 'required|in:employee,employer',
            'rate' => 'required|numeric|min:0|max:100',
            'cap' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $contribution = SocialContribution::create([
            'company_id' => $actor->company_id,
            'country_code' => $validated['country_code'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'rate' => $validated['rate'],
            'cap' => $validated['cap'] ?? null,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            // Issue #1813 : toute création passe par le workflow de validation.
            'status' => SocialContribution::STATUS_DRAFT,
        ]);

        $this->validation->logCreated($contribution, $actor);

        return (new SocialContributionResource($contribution))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('update', $socialContribution);

        // Issue #1813 : une ligne soumise/active ne se modifie plus directement.
        if ($socialContribution->status !== SocialContribution::STATUS_DRAFT) {
            abort(409, __('payroll.rate_edit_locked'));
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'type' => 'sometimes|in:employee,employer',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'cap' => 'nullable|numeric|min:0',
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

        $socialContribution->update($validated);

        return (new SocialContributionResource($socialContribution->refresh()))->response();
    }

    public function destroy(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('delete', $socialContribution);

        // Issue #1813 : seules les lignes draft peuvent être supprimées.
        if ($socialContribution->status !== SocialContribution::STATUS_DRAFT) {
            abort(409, __('payroll.rate_delete_draft_only'));
        }

        $socialContribution->delete();

        return response()->json(['message' => __('payroll.social_contribution_deleted')]);
    }

    /**
     * Soumet une ligne draft pour validation par un platform_admin.
     */
    public function submit(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('submit', $socialContribution);

        /** @var Employee $actor */
        $actor = $request->user();

        try {
            $this->validation->submit($socialContribution, $actor);
        } catch (\DomainException $e) {
            // #3810 : code stable — le message brut (règle métier interne) reste en logs.
            Log::error('social_contribution.submit_failed', ['contribution_id' => $socialContribution->id, 'error' => $e->getMessage()]);
            abort(422, __('errors.SOCIAL_CONTRIBUTION_SUBMIT_FAILED'));
        }

        return (new SocialContributionResource($socialContribution->refresh()))->response();
    }

    /**
     * Historique immuable des modifications d'une ligne (audit trail).
     */
    public function history(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        // Issue #1917 : Policy Laravel (manager + tenant isolation).
        $this->authorize('view', $socialContribution);

        return response()->json([
            'data' => $this->validation->history($socialContribution)
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
