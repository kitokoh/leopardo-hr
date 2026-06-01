<?php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\ApiFeature;
use App\Attributes\RequiresPermission;
use App\DTOs\CreateEmployeeDTO;
use App\DTOs\UpdateEmployeeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArchiveEmployeeRequest;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Absence;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\DataAccessAuditLogger;
use App\Services\EmployeeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly DataAccessAuditLogger $dataAccessAuditLogger,
    ) {}

    /**
     * Liste des employés avec pagination
     *
     * @title Liste des Employés
     *
     * @description Récupère la liste paginée de tous les employés de l'entreprise
     *
     * @permission employees.view
     *
     * @mobile true
     *
     * @ui list
     */
    #[ApiFeature(
        title: 'Liste des Employés',
        description: 'Récupère la liste paginée de tous les employés de l\'entreprise',
        ui_type: 'list',
        mobile_compatible: true
    )]
    #[RequiresPermission('employees.view')]
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', Employee::class);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,archived,suspended'],
            'role' => ['nullable', 'in:employee,manager,admin,super_admin'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:id,first_name,last_name,email,role,status'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $sortBy = (string) ($validated['sort_by'] ?? 'id');
        $sortDir = (string) ($validated['sort_dir'] ?? 'asc');

        $query = Employee::query()
            ->with([
                'company' => fn ($query) => $query->select($this->relationColumns('companies', [
                    'id',
                    'name',
                    'language',
                    'timezone',
                    'currency',
                    'features',
                ])),
                'schedule' => fn ($query) => $query->select($this->relationColumns('schedules', [
                    'id',
                    'name',
                    'start_time',
                    'end_time',
                    'break_minutes',
                    'late_tolerance_minutes',
                ])),
            ])
            ->select($this->employeeIndexColumns());

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['search'])) {
            $needle = '%'.addcslashes((string) $validated['search'], '%_\\').'%';
            $searchColumns = $this->relationColumns('employees', [
                'first_name',
                'last_name',
                'email',
                'matricule',
            ]);
            $query->where(function (Builder $query) use ($needle, $searchColumns): void {
                foreach ($searchColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', $needle);
                }
            });
        }

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id')
            ->paginate($perPage);

        $this->attachOperationalState($paginator->getCollection());

        $this->dataAccessAuditLogger->record($request, $actor, 'hr_data.employee_list_viewed', null, [
            'resource' => 'employees',
            'result_count' => $paginator->count(),
            'per_page' => $paginator->perPage(),
            'page' => $paginator->currentPage(),
        ]);

        return EmployeeResource::collection($paginator)->response();
    }

    /**
     * @return list<string>
     */
    private function employeeIndexColumns(): array
    {
        $columns = [
            'id',
            'matricule',
            'company_id',
            'schedule_id',
            'first_name',
            'middle_name',
            'last_name',
            'preferred_name',
            'email',
            'personal_email',
            'recovery_email',
            'personal_phone',
            'phone',
            'role',
            'manager_role',
            'status',
            'photo_path',
            'biometric_face_enabled',
            'biometric_fingerprint_enabled',
            'address_line',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_phone',
            'contract_start',
            'salary_type',
            'salary_base',
            'hourly_rate',
            'preferred_language',
            'extra_data',
        ];

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn('employees', $column)
        ));
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function relationColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($table, $column)
        ));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function attachOperationalState(Collection $employees): void
    {
        if ($employees->isEmpty()) {
            return;
        }

        $employeeIds = $employees->pluck('id')->all();
        $today = now()->toDateString();

        $approvedAbsenceIds = Absence::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $latestLogs = AttendanceLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->when(Schema::hasColumn('attendance_logs', 'date'), fn (Builder $query) => $query->whereDate('date', $today))
            ->when(Schema::hasColumn('attendance_logs', 'session_number'), fn (Builder $query) => $query->orderByDesc('session_number'))
            ->when(Schema::hasColumn('attendance_logs', 'check_in'), fn (Builder $query) => $query->orderByDesc('check_in'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($logs) => $logs->first());

        $employees->each(function (Employee $employee) use ($approvedAbsenceIds, $latestLogs): void {
            if ($employee->status !== 'active') {
                $employee->setAttribute('work_state', 'offline');
                $employee->setAttribute('work_state_label', 'Hors ligne');

                return;
            }

            if ($approvedAbsenceIds->has($employee->id)) {
                $employee->setAttribute('work_state', 'leave');
                $employee->setAttribute('work_state_label', 'En conge');

                return;
            }

            /** @var AttendanceLog|null $log */
            $log = $latestLogs->get($employee->id);

            if (! $log) {
                $employee->setAttribute('work_state', 'offline');
                $employee->setAttribute('work_state_label', 'Hors ligne');

                return;
            }

            if ($log->status === 'absent') {
                $employee->setAttribute('work_state', 'absent');
                $employee->setAttribute('work_state_label', 'Absent');

                return;
            }

            if ($log->check_out === null) {
                $state = match (true) {
                    $log->work_type === 'break' => 'break',
                    in_array($log->work_type, ['mission', 'travel'], true) => 'mission',
                    default => 'present',
                };
                $employee->setAttribute('work_state', $state);
                $employee->setAttribute('work_state_label', match ($state) {
                    'break' => 'En pause',
                    'mission' => 'En mission',
                    default => 'Present',
                });

                return;
            }

            $employee->setAttribute('work_state', 'offline');
            $employee->setAttribute('work_state_label', 'Hors ligne');
        });
    }

    /**
     * Créer un nouvel employé
     *
     * @title Créer un Employé
     *
     * @description Crée un nouvel employé dans le système
     *
     * @permission employees.create
     *
     * @mobile true
     *
     * @ui form
     */
    #[ApiFeature(
        title: 'Créer un Employé',
        description: 'Crée un nouvel employé dans le système',
        ui_type: 'form',
        mobile_compatible: true
    )]
    #[RequiresPermission('employees.create')]
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $employee = $this->employeeService->create(CreateEmployeeDTO::fromRequest($request), $actor);
        $employee->loadMissing('schedule:id,name,start_time,end_time,break_minutes,late_tolerance_minutes');

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Afficher les détails d'un employé
     *
     * @title Détails de l'Employé
     *
     * @description Affiche les informations détaillées d'un employé spécifique
     *
     * @permission employees.view
     *
     * @mobile true
     *
     * @ui detail
     */
    #[ApiFeature(
        title: 'Détails de l\'Employé',
        description: 'Affiche les informations détaillées d\'un employé spécifique',
        ui_type: 'detail',
        mobile_compatible: true
    )]
    #[RequiresPermission('employees.view')]
    public function show(string $employeeId, Request $request): JsonResponse
    {
        $employee = Employee::query()
            ->with([
                'company:id,name,language,timezone,currency,features',
                'schedule:id,name,start_time,end_time,break_minutes,late_tolerance_minutes',
            ])
            ->findOrFail($employeeId);

        $this->authorize('view', $employee);

        /** @var Employee $actor */
        $actor = $request->user();
        $this->dataAccessAuditLogger->record($request, $actor, 'hr_data.employee_profile_viewed', $employee, [
            'resource' => 'employee_profile',
            'target_employee_id' => $employee->id,
        ]);

        return (new EmployeeResource($employee))->response();
    }

    public function update(UpdateEmployeeRequest $request, string $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($actor, $employee, UpdateEmployeeDTO::fromRequest($request));
        $employee->loadMissing('schedule:id,name,start_time,end_time,break_minutes,late_tolerance_minutes');

        return (new EmployeeResource($employee))->response();
    }

    public function archive(ArchiveEmployeeRequest $request, string $employeeId): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('archive', $employee);

        $employee = $this->employeeService->archive($employee);

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'status' => $employee->status,
            ],
            'message' => 'EMPLOYEE_ARCHIVED',
        ]);
    }
}
