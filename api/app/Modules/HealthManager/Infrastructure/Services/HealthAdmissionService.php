<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Modules\HealthManager\Domain\Exceptions\HealthBedOccupiedException;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Machine à états des lits d'hospitalisation — HC-006 (#7790, spec §4).
 *
 * Invariants (toujours en transaction + `lockForUpdate` sur la ligne du
 * lit — aucune course possible entre deux admissions concurrentes) :
 *   - admission : lit du MÊME tenant, statut `free` requis → `occupied` ;
 *     lit occupé → 409 `HEALTH_BED_OCCUPIED` ; lit en maintenance → 422 ;
 *   - transfert : ancien lit libéré + nouveau occupé atomiquement (verrous
 *     pris par id croissant, anti-deadlock) ; admission sortie → 422 ;
 *   - sortie : `discharged_at` + statut `discharged` + lit libéré ;
 *     double sortie → 422.
 *
 * `health_beds.status` reste ainsi STRICTEMENT synchronisé avec les
 * admissions actives ; l'occupation par service est agrégée depuis les lits.
 */
final class HealthAdmissionService
{
    /**
     * Admission d'un patient sur un lit libre (spec §4).
     *
     * @param  array<string, mixed>  $data  patient_id, practitioner_id,
     *                                      bed_id (+ reason, admitted_at,
     *                                      expected_discharge_at)
     */
    public function admit(string $companyId, array $data): HealthAdmission
    {
        return DB::transaction(function () use ($companyId, $data): HealthAdmission {
            $bed = $this->lockBed($companyId, (int) $data['bed_id']);
            $this->assertBedFree($bed);

            /** @var HealthRoom $room */
            $room = HealthRoom::query()
                ->where('company_id', $companyId)
                ->whereKey($bed->room_id)
                ->firstOrFail();

            /** @var HealthAdmission $admission */
            $admission = HealthAdmission::query()->create([
                'company_id' => $companyId,
                'patient_id' => (int) $data['patient_id'],
                'practitioner_id' => (int) $data['practitioner_id'],
                'department_id' => $room->department_id,
                'bed_id' => (int) $bed->getAttribute('id'),
                'reason' => $data['reason'] ?? null,
                'admitted_at' => $data['admitted_at'] ?? now(),
                'expected_discharge_at' => $data['expected_discharge_at'] ?? null,
                'status' => HealthAdmission::STATUS_ADMITTED,
            ]);

            $bed->update(['status' => HealthBed::STATUS_OCCUPIED]);

            return $admission;
        });
    }

    /**
     * Transfert vers un nouveau lit : ancien libéré + nouveau occupé,
     * atomiquement (spec §4).
     */
    public function transfer(HealthAdmission $admission, int $newBedId): HealthAdmission
    {
        return DB::transaction(function () use ($admission, $newBedId): HealthAdmission {
            if ($admission->status === HealthAdmission::STATUS_DISCHARGED) {
                throw ValidationException::withMessages([
                    'status' => ['Une admission clôturée ne peut pas être transférée.'],
                ]);
            }

            $companyId = $admission->company_id;
            $oldBedId = $admission->bed_id;

            // Verrous par id croissant (anti-deadlock) : le nouveau lit
            // DOIT exister chez le tenant (sinon 404 fail-closed).
            $beds = HealthBed::query()
                ->where('company_id', $companyId)
                ->whereIn('id', array_unique([$oldBedId, $newBedId]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (HealthBed $bed): int => (int) $bed->getAttribute('id'));

            if ($newBedId === $oldBedId) {
                throw ValidationException::withMessages([
                    'bed_id' => ['Le patient occupe déjà ce lit.'],
                ]);
            }

            /** @var HealthBed|null $newBed */
            $newBed = $beds->get($newBedId);
            abort_if($newBed === null, 404);
            $this->assertBedFree($newBed);

            /** @var HealthBed|null $oldBed */
            $oldBed = $beds->get($oldBedId);
            $oldBed?->update(['status' => HealthBed::STATUS_FREE]);
            $newBed->update(['status' => HealthBed::STATUS_OCCUPIED]);

            /** @var HealthRoom $room */
            $room = HealthRoom::query()
                ->where('company_id', $companyId)
                ->whereKey($newBed->room_id)
                ->firstOrFail();

            $admission->update([
                'bed_id' => $newBedId,
                'department_id' => $room->department_id,
                'status' => HealthAdmission::STATUS_TRANSFERRED,
            ]);

            return $admission->refresh();
        });
    }

    /**
     * Sortie du patient : `discharged_at` + statut `discharged` + lit
     * libéré (spec §4). Double sortie → 422.
     */
    public function discharge(HealthAdmission $admission, ?string $dischargeNotes): HealthAdmission
    {
        return DB::transaction(function () use ($admission, $dischargeNotes): HealthAdmission {
            if ($admission->status === HealthAdmission::STATUS_DISCHARGED) {
                throw ValidationException::withMessages([
                    'status' => ['Cette admission est déjà clôturée.'],
                ]);
            }

            $bed = $this->lockBed($admission->company_id, $admission->bed_id);
            $bed->update(['status' => HealthBed::STATUS_FREE]);

            $admission->update([
                'status' => HealthAdmission::STATUS_DISCHARGED,
                'discharged_at' => now(),
                'discharge_notes' => $dischargeNotes,
            ]);

            return $admission->refresh();
        });
    }

    /**
     * Occupation des lits par service : total, occupés, libres, taux (%).
     *
     * @return array{data: list<array<string, mixed>>, meta: array{total_beds: int, occupied_beds: int, free_beds: int, occupancy_rate: float}}
     */
    public function occupancy(string $companyId): array
    {
        /** @var \Illuminate\Support\Collection<int, object{department_id: int, total: int, occupied: int, free: int}> $stats */
        $stats = HealthBed::query()
            ->join('health_rooms', 'health_rooms.id', '=', 'health_beds.room_id')
            ->where('health_beds.company_id', $companyId)
            ->where('health_rooms.company_id', $companyId)
            ->groupBy('health_rooms.department_id')
            ->selectRaw(
                'health_rooms.department_id as department_id, '
                .'count(*) as total, '
                .'sum(case when health_beds.status = ? then 1 else 0 end) as occupied, '
                .'sum(case when health_beds.status = ? then 1 else 0 end) as free',
                [HealthBed::STATUS_OCCUPIED, HealthBed::STATUS_FREE]
            )
            ->get()
            ->keyBy('department_id');

        $data = [];
        $totalBeds = 0;
        $occupiedBeds = 0;
        $freeBeds = 0;

        /** @var HealthDepartment $department */
        foreach (HealthDepartment::query()->where('company_id', $companyId)->orderBy('name')->get() as $department) {
            $row = $stats->get((int) $department->getAttribute('id'));
            $total = $row === null ? 0 : (int) $row->total;
            $occupied = $row === null ? 0 : (int) $row->occupied;
            $free = $row === null ? 0 : (int) $row->free;

            $totalBeds += $total;
            $occupiedBeds += $occupied;
            $freeBeds += $free;

            $data[] = [
                'department_id' => (int) $department->getAttribute('id'),
                'department_name' => $department->name,
                'total_beds' => $total,
                'occupied_beds' => $occupied,
                'free_beds' => $free,
                'occupancy_rate' => $this->rate($occupied, $total),
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'free_beds' => $freeBeds,
                'occupancy_rate' => $this->rate($occupiedBeds, $totalBeds),
            ],
        ];
    }

    /**
     * Verrouille la ligne du lit (même tenant, sinon 404 fail-closed).
     */
    private function lockBed(string $companyId, int $bedId): HealthBed
    {
        /** @var HealthBed $bed */
        $bed = HealthBed::query()
            ->where('company_id', $companyId)
            ->whereKey($bedId)
            ->lockForUpdate()
            ->firstOrFail();

        return $bed;
    }

    private function assertBedFree(HealthBed $bed): void
    {
        if ($bed->status === HealthBed::STATUS_OCCUPIED) {
            throw new HealthBedOccupiedException;
        }

        if ($bed->status !== HealthBed::STATUS_FREE) {
            throw ValidationException::withMessages([
                'bed_id' => ['Ce lit est indisponible (maintenance).'],
            ]);
        }
    }

    private function rate(int $occupied, int $total): float
    {
        return $total === 0 ? 0.0 : round($occupied * 100 / $total, 2);
    }
}
