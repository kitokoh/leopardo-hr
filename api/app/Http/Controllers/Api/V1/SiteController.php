<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        return response()->json(['data' => Site::orderBy('name')->get()->map(fn ($s) => $this->serialize($s))]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:500'], 'gps_lat' => ['nullable', 'numeric', 'between:-90,90'], 'gps_lng' => ['nullable', 'numeric', 'between:-180,180'], 'gps_radius_m' => ['nullable', 'integer', 'min:10', 'max:5000']]);
        $site = Site::create(['company_id' => $request->user()->company_id, ...$data]);

        return response()->json(['data' => $this->serialize($site)], 201);
    }

    public function show(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        return response()->json(['data' => $this->serialize($site)]);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:500'], 'gps_lat' => ['nullable', 'numeric', 'between:-90,90'], 'gps_lng' => ['nullable', 'numeric', 'between:-180,180'], 'gps_radius_m' => ['nullable', 'integer', 'min:10', 'max:5000']]);
        $site->update($data);

        return response()->json(['data' => $this->serialize($site->fresh())]);
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $site->delete();

        return response()->json(['message' => 'Site deleted successfully']);
    }

    private function serialize(Site $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'address' => $s->address, 'gps_lat' => $s->gps_lat, 'gps_lng' => $s->gps_lng, 'gps_radius_m' => $s->gps_radius_m, 'created_at' => $s->created_at?->toIso8601String()];
    }
}
