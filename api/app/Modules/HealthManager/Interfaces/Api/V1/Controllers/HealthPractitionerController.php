<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Application\Actions\DeleteHealthPractitionerAction;
use App\Modules\HealthManager\Application\Actions\RegisterHealthPractitionerAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthPractitionerAction;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des praticiens (référentiel équipe médicale) — HC-002 (#7786).
 *
 * Les spécialités (n-n) sont synchronisées via `specialty_ids` sur le
 * pivot `health_practitioner_specialties` (company_id porté par chaque
 * ligne — FK composites anti cross-tenant).
 */
class HealthPractitionerController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(
        private readonly RegisterHealthPractitionerAction $registerAction,
        private readonly UpdateHealthPractitionerAction $updateAction,
        private readonly DeleteHealthPractitionerAction $deleteAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthPractitioner::class);

        $query = HealthPractitioner::query()->where('company_id', $actor->company_id);

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $practitioners = $query->orderBy('id')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($practitioners->items())->map(fn (HealthPractitioner $practitioner): array => $this->payload($practitioner)),
            'meta' => [
                'current_page' => $practitioners->currentPage(),
                'per_page' => $practitioners->perPage(),
                'total' => $practitioners->total(),
            ],
        ]);
    }

    public function store(StoreHealthPractitionerRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthPractitioner::class);

        $practitioner = $this->registerAction->execute((string) $actor->company_id, $request->validated());

        return response()->json(['data' => $this->payload($practitioner)], 201);
    }

    public function show(Request $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('view', $practitioner);

        return response()->json(['data' => $this->payload($practitioner)]);
    }

    public function update(UpdateHealthPractitionerRequest $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('update', $practitioner);

        $practitioner = $this->updateAction->execute($practitioner, (string) $actor->company_id, $request->validated());

        return response()->json(['data' => $this->payload($practitioner)]);
    }

    public function destroy(Request $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('delete', $practitioner);

        $this->deleteAction->execute($practitioner);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthPractitioner $practitioner): array
    {
        return [
            'id' => (int) $practitioner->getAttribute('id'),
            'employee_id' => $practitioner->employee_id,
            'department_id' => $practitioner->department_id,
            'title' => $practitioner->title,
            'license_number' => $practitioner->license_number,
            'status' => $practitioner->status,
            'specialty_ids' => $practitioner->practitionerSpecialties()
                ->orderBy('specialty_id')
                ->pluck('specialty_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }
}
