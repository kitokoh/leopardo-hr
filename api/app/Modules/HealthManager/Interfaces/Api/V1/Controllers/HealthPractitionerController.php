<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthPractitionerRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des praticiens — HC-002 (#7786, BC-30). Praticien rattaché à un
 * employé RH (lien découplé) ; spécialités en n-n (pivot tenant-scoped).
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

        $query = HealthPractitioner::query()
            ->with('specialties')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('specialty_id')) {
            $specialtyId = (int) $request->input('specialty_id');
            $query->whereHas('specialties', function (Builder $specialties) use ($specialtyId): void {
                $specialties->whereKey($specialtyId);
            });
        }

        $practitioners = $query->orderBy('display_name')->paginate((int) ($request->input('per_page') ?? 15));

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
        /** @var list<int> $specialtyIds */
        $specialtyIds = array_map(intval(...), (array) ($validated['specialty_ids'] ?? []));
        unset($validated['specialty_ids']);

        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->create($validated);

        $this->syncSpecialties($practitioner, $specialtyIds, $actor->company_id);

        return response()->json(['data' => $this->payload($practitioner->load('specialties'))], 201);
    }

    public function show(Request $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('view', $practitioner);

        return response()->json(['data' => $this->payload($practitioner->load('specialties'))]);
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
        /** @var list<int> $specialtyIds */
        $specialtyIds = array_map(intval(...), (array) ($validated['specialty_ids'] ?? []));
        unset($validated['specialty_ids']);

        $practitioner->update($validated);

        if ($syncSpecialties) {
            $this->syncSpecialties($practitioner, $specialtyIds, $actor->company_id);
        }

        return response()->json(['data' => $this->payload($practitioner->refresh()->load('specialties'))]);
    }

    public function destroy(Request $request, HealthPractitioner $practitioner): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($practitioner, $actor->company_id);
        $this->authorize('delete', $practitioner);

        $practitioner->delete();

        return response()->json(null, 204);
    }

    /**
     * Le pivot porte `company_id` NON nullable (isolation en base) : les
     * valeurs pivot sont posées côté serveur, jamais depuis la requête.
     *
     * @param  list<int>  $specialtyIds
     */
    private function syncSpecialties(HealthPractitioner $practitioner, array $specialtyIds, string $companyId): void
    {
        $sync = [];
        foreach ($specialtyIds as $specialtyId) {
            $sync[$specialtyId] = ['company_id' => $companyId];
        }

        $practitioner->specialties()->sync($sync);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthPractitioner $practitioner): array
    {
        return [
            'id' => (int) $practitioner->getAttribute('id'),
            'employee_id' => $practitioner->employee_id !== null ? (int) $practitioner->employee_id : null,
            'display_name' => $practitioner->display_name,
            'license_number' => $practitioner->license_number,
            'title' => $practitioner->title,
            'status' => $practitioner->status,
            'specialties' => $practitioner->specialties->map(fn (HealthSpecialty $specialty): array => [
                'id' => (int) $specialty->getAttribute('id'),
                'code' => $specialty->code,
                'name' => $specialty->name,
            ])->values()->all(),
        ];
    }
}
