<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthPractitionerSpecialty;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $validated = $request->validated();
        /** @var list<int|string> $specialtyIds */
        $specialtyIds = $validated['specialty_ids'] ?? [];
        unset($validated['specialty_ids']);

        /** @var HealthPractitioner $practitioner */
        $practitioner = DB::transaction(function () use ($actor, $validated, $specialtyIds): HealthPractitioner {
            /** @var HealthPractitioner $practitioner */
            $practitioner = HealthPractitioner::query()->create(array_merge($validated, [
                'company_id' => $actor->company_id,
            ]));

            $this->syncSpecialties($practitioner, $specialtyIds, (string) $actor->company_id);

            return $practitioner;
        });

        return response()->json(['data' => $this->payload($practitioner->refresh())], 201);
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

        $validated = $request->validated();
        $syncSpecialties = array_key_exists('specialty_ids', $validated);
        /** @var list<int|string> $specialtyIds */
        $specialtyIds = $validated['specialty_ids'] ?? [];
        unset($validated['specialty_ids']);

        DB::transaction(function () use ($practitioner, $actor, $validated, $syncSpecialties, $specialtyIds): void {
            $practitioner->update($validated);

            if ($syncSpecialties) {
                $this->syncSpecialties($practitioner, $specialtyIds, (string) $actor->company_id);
            }
        });

        return response()->json(['data' => $this->payload($practitioner->refresh())]);
    }

    public function destroy(Request $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('delete', $practitioner);

        // Suppression bloquée si le praticien porte une activité clinique
        // (rendez-vous ou consultations) — 422 HEALTH_RESOURCE_IN_USE.
        if ($practitioner->appointments()->exists() || $practitioner->consultations()->exists()) {
            throw new HealthResourceInUseException;
        }

        DB::transaction(function () use ($practitioner): void {
            $practitioner->practitionerSpecialties()->delete();
            $practitioner->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Synchronise le pivot praticien ↔ spécialités (company_id sur chaque
     * ligne — jamais de rattachement cross-tenant, ids déjà validés).
     *
     * @param  list<int|string>  $specialtyIds
     */
    private function syncSpecialties(HealthPractitioner $practitioner, array $specialtyIds, string $companyId): void
    {
        $ids = array_values(array_unique(array_map(
            static fn (int|string $id): int => (int) $id,
            $specialtyIds
        )));

        HealthPractitionerSpecialty::query()
            ->where('company_id', $companyId)
            ->where('practitioner_id', $practitioner->getAttribute('id'))
            ->whereNotIn('specialty_id', $ids)
            ->delete();

        foreach ($ids as $specialtyId) {
            HealthPractitionerSpecialty::query()->firstOrCreate([
                'company_id' => $companyId,
                'practitioner_id' => (int) $practitioner->getAttribute('id'),
                'specialty_id' => $specialtyId,
            ]);
        }
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
