<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\SmartAttendance\Application\Actions\ApproveGeoSession;
use App\Modules\SmartAttendance\Application\Actions\RejectGeoSession;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use App\Modules\SmartAttendance\Interfaces\Api\V1\Requests\ApproveSessionRequest;
use App\Modules\SmartAttendance\Interfaces\Api\V1\Requests\RejectSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Manager/RH — gestion des sessions GPS (liste + validation).
 * Middleware: auth:sanctum + tenant + api.manager:rh,principal
 */
class GeoSessionController extends Controller
{
    public function __construct(
        private readonly ApproveGeoSession $approveAction,
        private readonly RejectGeoSession  $rejectAction,
    ) {}

    /**
     * GET /api/v1/smart-attendance/sessions
     * Liste des sessions GPS avec filtres optionnels.
     */
    public function index(Request $request): JsonResponse
    {
        $company = currentCompany();

        $query = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->with(['employee', 'site', 'validatedBy'])
            ->orderByDesc('started_at');

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->input('date_to'));
        }

        $sessions = $query->paginate(
            (int) $request->input('per_page', 20)
        );

        return response()->json([
            'data' => $sessions->map(fn ($s) => $this->formatSession($s)),
            'meta' => [
                'total'        => $sessions->total(),
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/smart-attendance/sessions/{id}
     * Détail d'une session.
     */
    public function show(int $id): JsonResponse
    {
        $company = currentCompany();

        $session = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->with(['employee', 'site', 'validatedBy', 'locationEvents'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatSession($session, detail: true),
        ]);
    }

    /**
     * GET /api/v1/smart-attendance/my-sessions
     * Mes propres sessions GPS (employé connecté).
     */
    public function mySessions(Request $request): JsonResponse
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = request()->user();
        $company  = currentCompany();

        $sessions = GeoAttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->where('company_id', $company->id)
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json([
            'data' => $sessions->map(fn ($s) => $this->formatSession($s)),
            'meta' => [
                'total'        => $sessions->total(),
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/smart-attendance/sessions/{id}/approve
     */
    public function approve(ApproveSessionRequest $request, int $id): JsonResponse
    {
        $company = currentCompany();

        $session = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                GeoAttendanceSession::STATUS_DETECTED,
                GeoAttendanceSession::STATUS_PENDING_VALIDATION,
            ])
            ->findOrFail($id);

        /** @var \App\Core\Auth\Domain\Models\Employee $validator */
        $validator = request()->user();

        $session = $this->approveAction->handle(
            session:   $session,
            validator: $validator,
            note:      $request->input('note'),
        );

        return response()->json([
            'message' => __('attendance.geo_session_approved'),
            'data'    => $this->formatSession($session),
        ]);
    }

    /**
     * POST /api/v1/smart-attendance/sessions/{id}/reject
     */
    public function reject(RejectSessionRequest $request, int $id): JsonResponse
    {
        $company = currentCompany();

        $session = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                GeoAttendanceSession::STATUS_DETECTED,
                GeoAttendanceSession::STATUS_PENDING_VALIDATION,
            ])
            ->findOrFail($id);

        /** @var \App\Core\Auth\Domain\Models\Employee $validator */
        $validator = request()->user();

        $session = $this->rejectAction->handle(
            session:   $session,
            validator: $validator,
            reason:    $request->input('reason'),
        );

        return response()->json([
            'message' => __('attendance.geo_session_rejected'),
            'data'    => $this->formatSession($session),
        ]);
    }

    /**
     * GET /api/v1/smart-attendance/dashboard
     * Statistiques du jour pour le dashboard manager/RH.
     */
    public function dashboard(): JsonResponse
    {
        $company = currentCompany();
        $today   = now()->toDateString();

        $stats = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->whereDate('started_at', $today)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $pending = GeoAttendanceSession::query()
            ->where('company_id', $company->id)
            ->where('status', GeoAttendanceSession::STATUS_PENDING_VALIDATION)
            ->with(['employee'])
            ->orderByDesc('ended_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'today'   => $today,
                'stats'   => $stats,
                'pending' => $pending->map(fn ($s) => $this->formatSession($s)),
            ],
        ]);
    }

    // ── Format ────────────────────────────────────────────────────────────────

    private function formatSession(GeoAttendanceSession $session, bool $detail = false): array
    {
        $base = [
            'id'                => $session->id,
            'employee'          => [
                'id'    => $session->employee_id,
                'name'  => $session->employee ? trim($session->employee->first_name . ' ' . $session->employee->last_name) : null,
                'photo' => $session->employee?->photo_path,
            ],
            'site'              => $session->site ? [
                'id'   => $session->site->id,
                'name' => $session->site->name,
            ] : null,
            'started_at'        => $session->started_at?->toIso8601String(),
            'ended_at'          => $session->ended_at?->toIso8601String(),
            'duration_seconds'  => $session->duration_seconds,
            'duration_formatted' => $session->durationFormatted(),
            'status'            => $session->status,
            'attendance_log_id' => $session->attendance_log_id,
            'validated_by'      => $session->validatedBy ? trim($session->validatedBy->first_name . ' ' . $session->validatedBy->last_name) : null,
            'validated_at'      => $session->validated_at?->toIso8601String(),
            'validation_note'   => $session->validation_note,
        ];

        if ($detail) {
            $base['check_in_lat']   = $session->check_in_lat;
            $base['check_in_lng']   = $session->check_in_lng;
            $base['check_out_lat']  = $session->check_out_lat;
            $base['check_out_lng']  = $session->check_out_lng;
            $base['location_events'] = $session->locationEvents->map(fn ($e) => [
                'event_type'       => $e->event_type,
                'latitude'         => $e->latitude,
                'longitude'        => $e->longitude,
                'accuracy_meters'  => $e->accuracy_meters,
                'device_timestamp' => $e->device_timestamp?->toIso8601String(),
                'created_at'       => $e->created_at?->toIso8601String(),
            ]);
        }

        return $base;
    }
}
