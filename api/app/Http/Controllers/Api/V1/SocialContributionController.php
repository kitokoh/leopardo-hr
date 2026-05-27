<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
use App\Models\Employee;
use App\Models\SocialContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Social\StoreSocialContributionRequest;
use App\Http\Requests\Api\V1\Social\UpdateSocialContributionRequest;

class SocialContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = SocialContribution::query();

        if ($request->filled('country_code')) {
            $query->forCountry($request->input('country_code'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return SocialContributionResource::collection($query->orderBy('country_code')->orderBy('type')->orderBy('name')->get())->response();
    }

    public function store(StoreSocialContributionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

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

    public function update(UpdateSocialContributionRequest $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

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
