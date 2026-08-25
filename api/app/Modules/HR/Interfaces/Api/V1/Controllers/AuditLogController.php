<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Support\CsvCellSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AuditLogController — immutable audit trail for principal managers.
 *
 * Migrated from App\Http\Controllers\Api\V1\AuditLogController.
 * Read-only (no store/update/destroy) — audit logs are append-only.
 * Restricted to hasManagerRole('principal').
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $this->canViewAuditLogs($actor)) {
            abort(403);
        }

        $query = AuditLog::query()
            ->forCompany($actor->company_id)
            ->with('user:id,first_name,last_name');

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        return AuditLogResource::collection(
            $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 25))))
        )->response();
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $this->canViewAuditLogs($actor)) {
            abort(403);
        }
        if ($auditLog->company_id !== $actor->company_id) {
            abort(404);
        }

        return (new AuditLogResource($auditLog->load('user:id,first_name,last_name')))->response();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $this->canViewAuditLogs($actor)) {
            abort(403);
        }

        $query = AuditLog::query()
            ->forCompany($actor->company_id)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('created_at');

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $filename = 'audit_logs_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'user', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'created_at']);

            $query->chunk(500, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    $userName = $log->user
                        ? $log->user->first_name.' '.$log->user->last_name
                        : '';

                    fputcsv($handle, [
                        $log->id,
                        // #4169 : les champs texte (user, action, valeurs JSON)
                        // sont neutralisés contre l'injection de formule CSV.
                        CsvCellSanitizer::neutralize($userName),
                        CsvCellSanitizer::neutralize((string) $log->action),
                        CsvCellSanitizer::neutralize((string) $log->auditable_type),
                        $log->auditable_id,
                        CsvCellSanitizer::neutralize(is_array($log->old_values) ? json_encode($log->old_values) : ($log->old_values ?? '')),
                        CsvCellSanitizer::neutralize(is_array($log->new_values) ? json_encode($log->new_values) : ($log->new_values ?? '')),
                        $log->created_at?->toIso8601String() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * RBAC #5439 — le journal d'audit global est lisible par le manager
     * principal ET le manager RH du tenant (l'employé reste exclu).
     */
    private function canViewAuditLogs(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
