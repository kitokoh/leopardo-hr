<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;
use App\Modules\EduManager\Infrastructure\Services\EduCourseSlotService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduCourseSlotRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduCourseSlotRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des créneaux d'emploi du temps — EDU-010 (issue #5826, EDU-006).
 */
class EduCourseSlotController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduCourseSlotService $slots)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduCourseSlot::class);

        $query = EduCourseSlot::query()->with(['class:id,code,name', 'subject:id,code,name'])
            ->where('company_id', $actor->company_id);

        if ($request->filled('class_id')) {
            $query->where('class_id', (int) $request->input('class_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', (int) $request->input('teacher_id'));
        }

        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', (int) $request->input('day_of_week'));
        }

        $slots = $query->orderBy('day_of_week')->orderBy('start_time')->get();

        return response()->json([
            'data' => $slots->map(fn (EduCourseSlot $slot): array => $this->payload($slot)),
        ]);
    }

    public function store(StoreEduCourseSlotRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduCourseSlot::class);

        $slot = $this->slots->create($actor, $request->validated());

        return response()->json(['data' => $this->payload($slot)], 201);
    }

    public function show(Request $request, EduCourseSlot $slot): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($slot, $actor->company_id);
        $this->authorize('view', $slot);

        return response()->json(['data' => $this->payload($slot)]);
    }

    public function update(UpdateEduCourseSlotRequest $request, EduCourseSlot $slot): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($slot, $actor->company_id);
        $this->authorize('update', $slot);

        $slot->update($request->validated());

        return response()->json(['data' => $this->payload($slot->refresh())]);
    }

    public function destroy(Request $request, EduCourseSlot $slot): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($slot, $actor->company_id);
        $this->authorize('delete', $slot);

        $slot->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduCourseSlot $slot): array
    {
        return [
            'id' => (int) $slot->getAttribute('id'),
            'class_id' => $slot->class_id,
            'subject_id' => $slot->subject_id,
            'academic_year_id' => $slot->academic_year_id,
            'teacher_id' => $slot->teacher_id,
            'day_of_week' => $slot->day_of_week,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'room' => $slot->room,
            'status' => $slot->status,
        ];
    }
}
