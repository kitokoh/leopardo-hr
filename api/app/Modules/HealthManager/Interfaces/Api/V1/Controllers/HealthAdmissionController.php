<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthBedUnavailableException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvalidStatusTransitionException;
use App\Modules\HealthManager\Domain\Exceptions\HealthPatientAlreadyAdmittedException;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\DischargeHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\TransferHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * API des hospitalisations — HC-006 (#7790, BC-30).
 *
 * Direction et accueil admettent, transfèrent et font sortir ; les
 * praticiens actifs consultent. La cohérence lit ↔ séjour est garantie
 * SOUS TRANSACTION (lockForUpdate sur le lit + index uniques partiels en
 * base) : un lit occupé ne peut pas être réaffecté (409
 * HEALTH_BED_UNAVAILABLE), la sortie et le transfert LIBÈRENT le lit,
 * le transfert est tracé (lit d'origine + horodatage).
 */
class HealthAdmissionController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAdmission::class);

        $query = HealthAdmission::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        $admissions = $query->orderByDesc('admitted_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($admissions->items())->map(fn (HealthAdmission $admission): array => $this->payload($admission)),
            'meta' => [
                'current_page' => $admissions->currentPage(),
                'per_page' => $admissions->perPage(),
                'total' => $admissions->total(),
            ],
        ]);
    }

    /**
     * Admission : le lit est verrouillé (lockForUpdate) DANS la
     * transaction — libre sinon 409 ; un patient n'a qu'un séjour actif
     * (409) ; le lit passe `occupied` avec le séjour, atomiquement.
     */
    public function store(StoreHealthAdmissionRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthAdmission::class);

        $validated = $request->validated();

        /** @var HealthAdmission $admission */
        $admission = DB::transaction(function () use ($validated, $actor): HealthAdmission {
            $bed = $this->lockFreeBed((string) $actor->company_id, (int) $validated['bed_id']);

            $alreadyAdmitted = HealthAdmission::query()
                ->where('company_id', $actor->company_id)
                ->where('patient_id', (int) $validated['patient_id'])
                ->whereIn('status', HealthAdmission::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();
            if ($alreadyAdmitted) {
                throw new HealthPatientAlreadyAdmittedException;
            }

            /** @var HealthAdmission $created */
            $created = new HealthAdmission([
                'patient_id' => (int) $validated['patient_id'],
                'practitioner_id' => (int) $validated['practitioner_id'],
                'department_id' => (int) $validated['department_id'],
                'reason' => (string) $validated['reason'],
                'admitted_at' => $validated['admitted_at'] ?? now(),
                'expected_discharge_at' => $validated['expected_discharge_at'] ?? null,
            ]);
            $created->bed_id = (int) $bed->getAttribute('id');
            $created->status = HealthAdmission::STATUS_ADMITTED;
            $created->save();

            $bed->update(['status' => HealthBed::STATUS_OCCUPIED]);

            return $created;
        });

        return response()->json(['data' => $this->payload($admission)], 201);
    }

    public function show(Request $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('view', $admission);

        return response()->json(['data' => $this->payload($admission)]);
    }

    /**
     * Transfert de lit d'un séjour ACTIF : le nouveau lit doit être libre
     * (verrouillé, 409 sinon), l'ancien est LIBÉRÉ, le transfert est tracé
     * (`transferred_from_bed_id`, `transferred_at`) — le tout dans UNE
     * transaction.
     */
    public function transfer(TransferHealthAdmissionRequest $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('transition', $admission);

        if (! $admission->isActive()) {
            throw new HealthInvalidStatusTransitionException($admission->status, HealthAdmission::STATUS_TRANSFERRED);
        }

        $targetBedId = (int) $request->validated()['bed_id'];

        DB::transaction(function () use ($admission, $targetBedId, $actor): void {
            $previousBedId = $admission->bed_id;
            if ($targetBedId === $previousBedId) {
                throw new HealthBedUnavailableException;
            }

            $newBed = $this->lockFreeBed((string) $actor->company_id, $targetBedId);

            $admission->bed_id = (int) $newBed->getAttribute('id');
            $admission->transferred_from_bed_id = $previousBedId;
            $admission->transferred_at = now();
            $admission->status = HealthAdmission::STATUS_TRANSFERRED;
            $admission->save();

            $newBed->update(['status' => HealthBed::STATUS_OCCUPIED]);
            $this->releaseBed((string) $actor->company_id, $previousBedId);
        });

        return response()->json(['data' => $this->payload($admission->refresh())]);
    }

    /**
     * Sortie d'hospitalisation : statut `discharged` (terminal), date de
     * sortie réelle posée, notes de sortie chiffrées, lit LIBÉRÉ — le tout
     * dans UNE transaction. Sortir un séjour déjà sorti → 422.
     */
    public function discharge(DischargeHealthAdmissionRequest $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('transition', $admission);

        if (! $admission->isActive()) {
            throw new HealthInvalidStatusTransitionException($admission->status, HealthAdmission::STATUS_DISCHARGED);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($admission, $validated, $actor): void {
            $admission->status = HealthAdmission::STATUS_DISCHARGED;
            $admission->discharged_at = isset($validated['discharged_at'])
                ? Carbon::parse((string) $validated['discharged_at'])
                : now();
            $admission->discharge_notes = $validated['discharge_notes'] ?? null;
            $admission->save();

            $this->releaseBed((string) $actor->company_id, $admission->bed_id);
        });

        return response()->json(['data' => $this->payload($admission->refresh())]);
    }

    /**
     * Occupation par service (HC-006) : lits totaux / occupés / libres /
     * en maintenance et taux d'occupation, calculés depuis l'état RÉEL des
     * lits (health_beds via health_rooms), borné au tenant.
     */
    public function occupancy(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAdmission::class);

        /** @var list<object{department_id: int, department_name: string, total_beds: int|string, occupied_beds: int|string, free_beds: int|string, maintenance_beds: int|string}> $rows */
        $rows = DB::table('health_departments')
            ->leftJoin('health_rooms', function ($join) use ($actor): void {
                $join->on('health_rooms.department_id', '=', 'health_departments.id')
                    ->where('health_rooms.company_id', '=', $actor->company_id);
            })
            ->leftJoin('health_beds', function ($join) use ($actor): void {
                $join->on('health_beds.room_id', '=', 'health_rooms.id')
                    ->where('health_beds.company_id', '=', $actor->company_id);
            })
            ->where('health_departments.company_id', $actor->company_id)
            ->groupBy('health_departments.id', 'health_departments.name')
            ->orderBy('health_departments.name')
            ->selectRaw(
                'health_departments.id as department_id, health_departments.name as department_name, '
                .'count(health_beds.id) as total_beds, '
                ."count(health_beds.id) filter (where health_beds.status = 'occupied') as occupied_beds, "
                ."count(health_beds.id) filter (where health_beds.status = 'free') as free_beds, "
                ."count(health_beds.id) filter (where health_beds.status = 'maintenance') as maintenance_beds"
            )
            ->get()
            ->all();

        $data = array_map(static function (object $row): array {
            $total = (int) $row->total_beds;
            $occupied = (int) $row->occupied_beds;

            return [
                'department_id' => (int) $row->department_id,
                'department_name' => (string) $row->department_name,
                'total_beds' => $total,
                'occupied_beds' => $occupied,
                'free_beds' => (int) $row->free_beds,
                'maintenance_beds' => (int) $row->maintenance_beds,
                'occupancy_rate' => $total > 0 ? round($occupied / $total, 4) : 0.0,
            ];
        }, $rows);

        return response()->json(['data' => $data]);
    }

    /**
     * Verrouille le lit (lockForUpdate) et exige qu'il soit LIBRE — un lit
     * occupé ou en maintenance ne peut pas être affecté (409). Deux
     * admissions concurrentes du même lit se sérialisent sur ce verrou (la
     * seconde voit `occupied` et échoue) ; l'index unique partiel en base
     * est le filet ultime.
     */
    private function lockFreeBed(string $companyId, int $bedId): HealthBed
    {
        /** @var HealthBed|null $bed */
        $bed = HealthBed::query()
            ->where('company_id', $companyId)
            ->where('id', $bedId)
            ->lockForUpdate()
            ->first();

        if ($bed === null || $bed->status !== HealthBed::STATUS_FREE) {
            throw new HealthBedUnavailableException;
        }

        return $bed;
    }

    /**
     * Libère un lit occupé (fin de séjour ou transfert). Un lit passé en
     * maintenance entre-temps n'est PAS remis en service ici.
     */
    private function releaseBed(string $companyId, int $bedId): void
    {
        HealthBed::query()
            ->where('company_id', $companyId)
            ->where('id', $bedId)
            ->where('status', HealthBed::STATUS_OCCUPIED)
            ->lockForUpdate()
            ->get()
            ->each(fn (HealthBed $bed) => $bed->update(['status' => HealthBed::STATUS_FREE]));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthAdmission $admission): array
    {
        return [
            'id' => (int) $admission->getAttribute('id'),
            'patient_id' => $admission->patient_id,
            'practitioner_id' => $admission->practitioner_id,
            'department_id' => $admission->department_id,
            'bed_id' => $admission->bed_id,
            'transferred_from_bed_id' => $admission->transferred_from_bed_id,
            'reason' => $admission->reason,
            'admitted_at' => $admission->admitted_at->toISOString(),
            'expected_discharge_at' => $admission->expected_discharge_at?->toISOString(),
            'discharged_at' => $admission->discharged_at?->toISOString(),
            'transferred_at' => $admission->transferred_at?->toISOString(),
            'status' => $admission->status,
            'discharge_notes' => $admission->discharge_notes,
        ];
    }
}
