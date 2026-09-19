<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord clinique — HC-008 (#7792, BC-30).
 *
 * Contrat consommé par `front/web/src/lib/health-api.ts` (type
 * `HealthDashboard`) : les noms de champs sont contractuels
 * (appointments_today, patients_count, admissions_active, occupancy,
 * month_revenue, currency, recent_patients).
 *
 * Accès : tout rôle clinique authentifié (direction, praticien, réception,
 * facturation) — employé lambda 403 (deny-by-default, cohérent policies).
 * Aucun contenu médical exposé (agrégats + identité administrative).
 */
class HealthDashboardController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_unless(
            HealthAccess::isAdmin($actor)
            || HealthAccess::isPractitioner($actor)
            || HealthAccess::isReception($actor)
            || HealthAccess::isBilling($actor),
            403,
            'HEALTH_FORBIDDEN'
        );

        $companyId = (string) $actor->company_id;

        // Rendez-vous du jour (hors annulés/no-show — charge réelle du jour).
        $appointmentsToday = HealthAppointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('status', [HealthAppointment::STATUS_CANCELLED, HealthAppointment::STATUS_NO_SHOW])
            ->count();

        $patientsCount = HealthPatient::query()
            ->where('company_id', $companyId)
            ->where('status', HealthPatient::STATUS_ACTIVE)
            ->count();

        $admissionsActive = HealthAdmission::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [HealthAdmission::STATUS_ADMITTED, HealthAdmission::STATUS_TRANSFERRED])
            ->count();

        // Chiffre du mois : Σ encaissements du mois courant (spec HC-007/HC-008).
        $monthRevenue = (float) HealthInvoicePayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        /** @var list<array<string, mixed>> $recentPatients */
        $recentPatients = HealthPatient::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (HealthPatient $patient): array => [
                'id' => (int) $patient->getAttribute('id'),
                'mrn' => $patient->mrn,
                'full_name' => $patient->full_name,
                'sex' => $patient->sex,
                'status' => $patient->status,
                'created_at' => $patient->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'appointments_today' => $appointmentsToday,
                'patients_count' => $patientsCount,
                'admissions_active' => $admissionsActive,
                'occupancy' => $this->occupancy($companyId),
                'month_revenue' => round($monthRevenue, 2),
                'currency' => currentCompany()->currency,
                'recent_patients' => $recentPatients,
            ],
        ]);
    }

    /**
     * Occupation des lits (totaux + ventilation par service) — agrégée en
     * PHP (volumes bornés : lits d'un seul tenant) pour rester indépendante
     * du search_path des jointures SQL inter-schémas.
     *
     * @return array<string, mixed>
     */
    private function occupancy(string $companyId): array
    {
        /** @var \Illuminate\Support\Collection<int, HealthBed> $beds */
        $beds = HealthBed::query()->where('company_id', $companyId)->get();

        /** @var array<int, int> $roomToDepartment */
        $roomToDepartment = HealthRoom::query()
            ->where('company_id', $companyId)
            ->pluck('department_id', 'id')
            ->map(fn (mixed $departmentId): int => (int) $departmentId)
            ->all();

        /** @var array<int, string> $departmentNames */
        $departmentNames = HealthDepartment::query()
            ->where('company_id', $companyId)
            ->pluck('name', 'id')
            ->all();

        $total = $beds->count();
        $occupied = $beds->where('status', HealthBed::STATUS_OCCUPIED)->count();
        $free = $beds->where('status', HealthBed::STATUS_FREE)->count();
        $maintenance = $beds->where('status', HealthBed::STATUS_MAINTENANCE)->count();

        /** @var array<int, array{department_id: int, department_name: string, total: int, occupied: int}> $byDepartment */
        $byDepartment = [];

        foreach ($beds as $bed) {
            $departmentId = $roomToDepartment[$bed->room_id] ?? null;

            if ($departmentId === null) {
                continue;
            }

            $byDepartment[$departmentId] ??= [
                'department_id' => $departmentId,
                'department_name' => $departmentNames[$departmentId] ?? '',
                'total' => 0,
                'occupied' => 0,
            ];

            $byDepartment[$departmentId]['total']++;

            if ($bed->status === HealthBed::STATUS_OCCUPIED) {
                $byDepartment[$departmentId]['occupied']++;
            }
        }

        return [
            'total_beds' => $total,
            'occupied_beds' => $occupied,
            'free_beds' => $free,
            'maintenance_beds' => $maintenance,
            'occupancy_rate' => $total > 0 ? round($occupied / $total, 2) : 0.0,
            'by_department' => array_values($byDepartment),
        ];
    }
}
