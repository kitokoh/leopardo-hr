<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isManager()) {
            abort(403);
        }

        return response()->json(['data' => Schedule::orderBy('name')->get()->map(fn ($s) => $this->serialize($s))]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'], 'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'], 'work_days' => ['nullable', 'array'], 'work_days.*' => ['integer', 'between:1,7'], 'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'], 'overtime_threshold_daily' => ['nullable', 'numeric', 'min:0'], 'overtime_threshold_weekly' => ['nullable', 'numeric', 'min:0'], 'is_default' => ['nullable', 'boolean']]);

        if (! empty($data['is_default'])) {
            Schedule::where('company_id', $actor->company_id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule = Schedule::create(['company_id' => $actor->company_id, 'work_days' => $data['work_days'] ?? [1, 2, 3, 4, 5], ...$data]);

        return response()->json(['data' => $this->serialize($schedule)], 201);
    }

    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        if (! $request->user()->isManager()) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($schedule)]);
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'start_time' => ['sometimes', 'date_format:H:i'], 'end_time' => ['sometimes', 'date_format:H:i'], 'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'], 'work_days' => ['nullable', 'array'], 'work_days.*' => ['integer', 'between:1,7'], 'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'], 'overtime_threshold_daily' => ['nullable', 'numeric', 'min:0'], 'overtime_threshold_weekly' => ['nullable', 'numeric', 'min:0'], 'is_default' => ['nullable', 'boolean']]);

        if (! empty($data['is_default'])) {
            Schedule::where('company_id', $actor->company_id)->where('id', '!=', $schedule->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule->update($data);

        return response()->json(['data' => $this->serialize($schedule->fresh())]);
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        if (! $request->user()->isManager()) {
            abort(403);
        }
        if ($schedule->is_default) {
            abort(422, 'Cannot delete the default schedule.');
        }

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }

    private function serialize(Schedule $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'break_minutes' => $s->break_minutes, 'work_days' => $s->work_days, 'late_tolerance_minutes' => $s->late_tolerance_minutes, 'overtime_threshold_daily' => $s->overtime_threshold_daily, 'overtime_threshold_weekly' => $s->overtime_threshold_weekly, 'is_default' => $s->is_default, 'created_at' => $s->created_at?->toIso8601String()];
    }
}
