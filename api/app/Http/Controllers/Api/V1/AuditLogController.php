<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $query = AuditLog::query()
            ->forCompany($actor->company_id)
            ->with('user:id,first_name,last_name');

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

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($auditLog->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => $auditLog->load('user:id,first_name,last_name')]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $query = AuditLog::query()
            ->forCompany($actor->company_id)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'user', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'created_at']);

            $query->chunk(500, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    $userName = $log->user
                        ? $log->user->first_name . ' ' . $log->user->last_name
                        : '';

                    fputcsv($handle, [
                        $log->id,
                        $userName,
                        $log->action,
                        $log->auditable_type,
                        $log->auditable_id,
                        is_array($log->old_values) ? json_encode($log->old_values) : ($log->old_values ?? ''),
                        is_array($log->new_values) ? json_encode($log->new_values) : ($log->new_values ?? ''),
                        $log->created_at?->toIso8601String() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
