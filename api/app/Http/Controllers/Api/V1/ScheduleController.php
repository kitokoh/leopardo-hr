<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Schedule\StoreScheduleRequest;
use App\Http\Requests\Api\V1\Schedule\UpdateScheduleRequest;
use App\Http\Resources\Api\V1\ScheduleResource;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return ScheduleResource::collection(Schedule::orderBy('name')->get());
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! empty($request->validated('is_default'))) {
            Schedule::where('company_id', $actor->company_id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule = Schedule::create([
            'company_id' => $actor->company_id,
            'work_days' => $request->validated('work_days') ?? [1, 2, 3, 4, 5],
            ...$request->validated(),
        ]);

        return (new ScheduleResource($schedule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Schedule $schedule): ScheduleResource
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return new ScheduleResource($schedule);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! empty($request->validated('is_default'))) {
            Schedule::where('company_id', $actor->company_id)->where('id', '!=', $schedule->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule->update($request->validated());

        return new ScheduleResource($schedule->fresh());
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }
        if ($schedule->is_default) {
            abort(422, 'Cannot delete the default schedule.');
        }

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
