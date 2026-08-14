<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialContributionController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $validation) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        // PA2-ARCH-004: uniqueness is scoped to (company_id, code,
        // effective_from) rather than a bare global code, so a new dated
        // rate for an existing contribution code can be added without
        // deleting the historical row a past payroll run needs to be
        // recalculated against for an audit. See the
        // make_social_contributions_code_unique_per_effective_period
        // migration.
        $validated = $request->validate([
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN,CM,CF,TD,CG,GA,GQ,CI,ML,BF,BJ,TG,NE,CA',
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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        // Issue #1813 : une ligne soumise/active ne se modifie plus directement.
        if ($socialContribution->status !== SocialContribution::STATUS_DRAFT) {
            abort(409, 'Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.');
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
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        // Issue #1813 : seules les lignes draft peuvent être supprimées.
        if ($socialContribution->status !== SocialContribution::STATUS_DRAFT) {
            abort(409, 'Seule une ligne en brouillon peut être supprimée.');
        }

        $socialContribution->delete();

        return response()->json(['message' => 'Social contribution deleted successfully.']);
    }

    /**
     * Soumet une ligne draft pour validation par un platform_admin.
     */
    public function submit(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        if ((string) $socialContribution->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        try {
            $this->validation->submit($socialContribution, $actor);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return (new SocialContributionResource($socialContribution->refresh()))->response();
    }

    /**
     * Historique immuable des modifications d'une ligne (audit trail).
     */
    public function history(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }
        if ((string) $socialContribution->company_id !== (string) $actor->company_id) {
            abort(404);
        }

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
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
