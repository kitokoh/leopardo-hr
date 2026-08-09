<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
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
        ]);

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

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'type' => 'sometimes|in:employee,employer',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'cap' => 'nullable|numeric|min:0',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date',
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
