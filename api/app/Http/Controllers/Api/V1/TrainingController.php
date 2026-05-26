<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingCourseResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Http\Resources\Api\V1\TrainingSessionResource;
use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingController extends Controller
{
    // ── Courses ─────────────────────────────────────────────────────────────

    public function indexCourses(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = TrainingCourse::query()->where('company_id', $actor->company_id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return TrainingCourseResource::collection($query->orderBy('title')->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function storeCourse(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'type' => 'required|in:internal,external,online,certification',
            'provider' => 'nullable|string|max:200',
            'duration_hours' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'cost_per_participant' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
        ]);

        $course = TrainingCourse::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

        return (new TrainingCourseResource($course))
            ->response()
            ->setStatusCode(201);
    }

    public function showCourse(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($trainingCourse->company_id !== $user->company_id) {
            abort(404);
        }

        return (new TrainingCourseResource($trainingCourse->load('sessions')))->response();
    }

    public function updateCourse(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'type' => 'sometimes|in:internal,external,online,certification',
            'provider' => 'nullable|string|max:200',
            'duration_hours' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'cost_per_participant' => 'nullable|numeric|min:0',
            'active' => 'boolean',
        ]);

        $trainingCourse->update($validated);

        return (new TrainingCourseResource($trainingCourse->fresh()))->response();
    }

    // ── Sessions ────────────────────────────────────────────────────────────

    public function indexSessions(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($trainingCourse->company_id !== $user->company_id) {
            abort(404);
        }

        return TrainingSessionResource::collection($trainingCourse->sessions()->with('trainer:id,first_name,last_name')->orderByDesc('start_date')->get())
            ->response();
    }

    public function storeSession(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
            'external_trainer' => 'nullable|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        $session = TrainingSession::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'training_course_id' => $trainingCourse->id,
        ]);

        return (new TrainingSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function updateSession(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
            'external_trainer' => 'nullable|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'location' => 'nullable|string|max:200',
            'status' => 'sometimes|in:planned,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $trainingSession->update($validated);

        return (new TrainingSessionResource($trainingSession->fresh()))->response();
    }

    // ── Enrollments ─────────────────────────────────────────────────────────

    public function enroll(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
        ]);

        $enrollment = TrainingEnrollment::firstOrCreate([
            'training_session_id' => $trainingSession->id,
            'employee_id' => $validated['employee_id'],
        ], [
            'company_id' => $actor->company_id,
            'status' => 'enrolled',
        ]);

        return (new TrainingEnrollmentResource($enrollment->load('employee:id,first_name,last_name')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateEnrollment(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingEnrollment->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:enrolled,attended,completed,no_show,cancelled',
            'score' => 'nullable|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $trainingEnrollment->update($validated);

        return (new TrainingEnrollmentResource($trainingEnrollment->fresh()))->response();
    }
}
