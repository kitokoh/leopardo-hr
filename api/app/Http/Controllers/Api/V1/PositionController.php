<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Position\StorePositionRequest;
use App\Http\Requests\Api\V1\Position\UpdatePositionRequest;
use App\Http\Resources\Api\V1\PositionResource;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PositionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $query = Position::query()
            ->select(['id', 'company_id', 'name', 'department_id', 'created_at'])
            ->with('department:id,name');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return PositionResource::collection($query->orderBy('name')->get());
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $pos = Position::create(['company_id' => $user->company_id, ...$request->validated()]);

        return (new PositionResource($pos))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Position $position): PositionResource
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return new PositionResource($position->load('department'));
    }

    public function update(UpdatePositionRequest $request, Position $position): PositionResource
    {
        $position->update($request->validated());

        return new PositionResource($position->fresh());
    }

    public function destroy(Request $request, Position $position): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $position->delete();

        return response()->json(['message' => 'Position deleted successfully']);
    }
}
