<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CareerEventResource;
use App\Modules\HR\Domain\Models\CareerEvent;
use App\Modules\HR\Interfaces\Api\V1\Requests\CareerEventIndexRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\RejectCareerEventRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreCareerEventRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateCareerEventRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Module HR — Plans de carrière (issue #5259).
 *
 * Événements de carrière : promotion, augmentation, transfert, changement de
 * poste. Workflow : pending → approved → applied (ou rejected).
 *
 * RBAC (PA2-SEC-002/003, aligné EvaluationPolicy) :
 *  - Manager : CRUD complet + transitions ; manager_role=dept scopé à son
 *    département, superviseur à son équipe directe.
 *  - Employé : lecture seule de ses propres événements.
 *
 * Impact paie : le passage à `applied` met à jour l'employé (position_id,
 * department_id, salary_base) dans une transaction — le prochain run de paie
 * consomme le nouveau brut sans intervention manuelle.
 */
class CareerEventController extends Controller
{
    public function index(CareerEventIndexRequest $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = CareerEvent::query()
            ->with([
                'employee:id,first_name,last_name,email',
                'fromPosition:id,name',
                'toPosition:id,name',
                'fromDepartment:id,name',
                'toDepartment:id,name',
            ]);

        if (! $actor->isManager()) {
            // Employé : uniquement son propre parcours.
            $query->where('employee_id', $actor->id);
        } else {
            if ($actor->isTeamScoped()) {
                // manager_role=dept → employés du département (PA2-SEC-002) ;
                // manager_role=superviseur → équipe directe (PA2-SEC-003).
                $scopedEmployeeIds = Employee::query()
                    ->where('company_id', $actor->company_id)
                    ->visibleToManager($actor)
                    ->pluck('id');
                $query->whereIn('employee_id', $scopedEmployeeIds);
            }
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->integer('employee_id'));
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));

        return CareerEventResource::collection($query->orderByDesc('effective_date')->paginate($perPage));
    }

    public function store(StoreCareerEventRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('create', CareerEvent::class);

        $data = $request->validated();

        $target = Employee::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['employee_id']);

        if ($actor->isTeamScoped() && ! $actor->managesTeamMemberOf($target)) {
            abort(403);
        }

        $event = CareerEvent::create([
            'company_id' => $actor->company_id,
            'employee_id' => $target->id,
            'type' => $data['type'],
            'status' => 'pending',
            // Snapshot de l'état courant de l'employé au moment de la création
            // (traçabilité de → vers, spec #5259 §3).
            'from_position_id' => $target->position_id,
            'from_department_id' => $target->department_id,
            'from_salary' => $target->salary_base,
            'to_position_id' => $data['to_position_id'] ?? null,
            'to_department_id' => $data['to_department_id'] ?? null,
            'to_salary' => $data['to_salary'] ?? null,
            'effective_date' => $data['effective_date'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
        ]);

        return (new CareerEventResource($event->load(
            'employee:id,first_name,last_name',
            'fromPosition:id,name',
            'toPosition:id,name',
            'fromDepartment:id,name',
            'toDepartment:id,name',
        )))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CareerEvent $event): CareerEventResource
    {
        $this->authorize('view', $event);

        return new CareerEventResource($event->load(
            'employee:id,first_name,last_name,email',
            'fromPosition:id,name',
            'toPosition:id,name',
            'fromDepartment:id,name',
            'toDepartment:id,name',
            'approver:id,first_name,last_name',
        ));
    }

    public function update(UpdateCareerEventRequest $request, CareerEvent $event): CareerEventResource
    {
        $this->authorize('update', $event);

        $data = $request->validated();

        // Whitelist explicite : l'employé et le snapshot from_* sont immuables.
        $event->update(collect($data)->only([
            'type', 'to_position_id', 'to_department_id',
            'to_salary', 'effective_date', 'reason', 'notes',
        ])->all());

        return new CareerEventResource($event->fresh()->load(
            'employee:id,first_name,last_name',
            'fromPosition:id,name',
            'toPosition:id,name',
            'fromDepartment:id,name',
            'toDepartment:id,name',
        ));
    }

    public function approve(Request $request, CareerEvent $event): CareerEventResource
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('approve', $event);

        $event->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
        ]);

        return new CareerEventResource($event->fresh()->load(
            'employee:id,first_name,last_name',
            'approver:id,first_name,last_name',
        ));
    }

    public function reject(RejectCareerEventRequest $request, CareerEvent $event): CareerEventResource
    {
        $this->authorize('reject', $event);

        $data = $request->validated();

        $notes = $event->notes;
        if (! empty($data['reason'])) {
            $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '').'Rejet — '.$data['reason']);
        }

        $event->update(['status' => 'rejected', 'notes' => $notes]);

        return new CareerEventResource($event->fresh()->load(
            'employee:id,first_name,last_name',
        ));
    }

    public function apply(Request $request, CareerEvent $event): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('apply', $event);

        if ($event->to_position_id === null && $event->to_department_id === null && $event->to_salary === null) {
            return response()->json(['error' => ['code' => 'CAREER_EVENT_NOTHING_TO_APPLY', 'message' => __('employees.career_event_nothing_to_apply')]], 422);
        }

        $target = Employee::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($event->employee_id);

        // #4978 : transaction imbriquée/savepoint — la mise à jour employé et
        // le passage à `applied` sont atomiques (jamais d'état à moitié).
        $event = DB::transaction(function () use ($event, $target): CareerEvent {
            $changes = [];
            if ($event->to_position_id !== null) {
                $changes['position_id'] = $event->to_position_id;
            }
            if ($event->to_department_id !== null) {
                $changes['department_id'] = $event->to_department_id;
            }
            if ($event->to_salary !== null) {
                // Impact paie : le prochain run consomme salary_base
                // (spec #5259 §5 — pas de changement moteur).
                $changes['salary_base'] = $event->to_salary;
            }
            $target->update($changes);

            $event->update(['status' => 'applied', 'applied_at' => Carbon::now()]);

            /** @var CareerEvent $fresh */
            $fresh = $event->fresh();

            return $fresh;
        });

        return (new CareerEventResource($event->load(
            'employee:id,first_name,last_name',
            'fromPosition:id,name',
            'toPosition:id,name',
            'fromDepartment:id,name',
            'toDepartment:id,name',
        )))->response();
    }

    public function destroy(Request $request, CareerEvent $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => __('employees.career_event_deleted')]);
    }
}
