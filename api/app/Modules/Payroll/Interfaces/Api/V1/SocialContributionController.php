<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
use App\Modules\Payroll\Application\Services\TaxRateValidationWorkflow;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialContributionController extends Controller
{
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

    public function __construct(
        private readonly TaxRateValidationWorkflow $validationWorkflow,
    ) {}

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
        /** @var array<string, mixed> $validated */
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

        // ADMIN-PAIE (#1813) : toute création passe par le workflow de
        // validation — la ligne naît en `draft` et n'entre dans les calculs
        // qu'après soumission + approbation par un platform_admin.
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
            'status' => TaxRateValidationWorkflow::STATUS_DRAFT,
        ]);

        $this->validationWorkflow->recordCreation($contribution, $actor);

        return (new SocialContributionResource($contribution))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * ADMIN-PAIE (#1813) — soumission d'une cotisation : draft → pending_validation.
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

        $contribution = $this->validationWorkflow->submit($socialContribution, $actor);

        return (new SocialContributionResource($contribution))->response();
    }

    /**
     * ADMIN-PAIE (#1813) — lecture de l'audit trail immuable de la cotisation.
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

        return response()->json(['data' => $this->validationWorkflow->history($socialContribution)]);
    }

    public function update(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
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

        $socialContribution->delete();

        return response()->json(['message' => 'Social contribution deleted successfully.']);
    }
}
