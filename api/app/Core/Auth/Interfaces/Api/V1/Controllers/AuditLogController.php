<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\CompanySetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * feat(tenant): GET /audit-logs — issue #9333.
 *
 * Permet au principal de consulter la piste d'audit de son tenant
 * (obligation RGPD : « qui a consulté la fiche de X ? »).
 *
 * Réservé au principal via gate `viewTenantAudit` (ou grant dédié).
 * Scope tenant appliqué par BelongsToCompany (un tenant ne lit jamais
 * les logs d'un autre).
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/v1/audit-logs
     * Liste paginée avec filtres combinables.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        
        // Gate : principal uniquement (ou grant dédié future)
        abort_unless(
            Gate::forUser($actor)->allows('viewTenantAudit'),
            403,
            __('auth.audit_access_principal_only')
        );

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'actor' => ['sometimes', 'integer', 'exists:employees,id'],
            'action' => ['sometimes', 'string', 'max:200'],
            'resource_type' => ['sometimes', 'string', 'max:200'],
            'resource_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at');

        // BelongsToCompany applique le scope automatiquement

        if (isset($validated['actor'])) {
            $query->where('user_id', $validated['actor']);
        }

        if (isset($validated['action'])) {
            $query->where('action', 'like', '%' . $validated['action'] . '%');
        }

        if (isset($validated['resource_type'])) {
            $query->where('auditable_type', $validated['resource_type']);
        }

        if (isset($validated['resource_id'])) {
            $query->where('auditable_id', $validated['resource_id']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 50;
        $logs = $query->paginate($perPage);

        // Rétention depuis CompanySetting
        $retention = $this->getRetentionMonths();

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'retention_months' => $retention,
            ],
        ]);
    }

    /**
     * GET /api/v1/audit-logs/{id}
     * Détail d'une entrée.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        
        abort_unless(
            Gate::forUser($actor)->allows('viewTenantAudit'),
            403,
            __('auth.audit_access_principal_only')
        );

        // BelongsToCompany protège le scope
        $log = AuditLog::query()
            ->with('user:id,first_name,last_name,email')
            ->findOrFail($id);

        return response()->json(['data' => $log]);
    }

    /**
     * GET /api/v1/audit-logs/export
     * Export CSV limité et audité.
     */
    public function export(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        
        abort_unless(
            Gate::forUser($actor)->allows('viewTenantAudit'),
            403,
            __('auth.audit_access_principal_only')
        );

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'action' => ['sometimes', 'string', 'max:200'],
        ]);

        // Limiter l'export à 10 000 lignes pour éviter les timeouts
        $query = AuditLog::query()
            ->with('user:id,first_name,last_name,email')
            ->whereDate('created_at', '>=', $validated['date_from'])
            ->whereDate('created_at', '<=', $validated['date_to'])
            ->orderByDesc('created_at')
            ->limit(10000);

        if (isset($validated['action'])) {
            $query->where('action', 'like', '%' . $validated['action'] . '%');
        }

        $logs = $query->get();

        // Auditer l'export
        AuditLog::record(
            module: 'audit',
            action: 'audit_logs.export',
            actor: $actor,
            metadata: [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'count' => $logs->count(),
            ],
        );

        // Générer CSV
        $csv = $this->generateCsv($logs);

        return response()->json([
            'data' => [
                'csv' => $csv,
                'count' => $logs->count(),
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
            ],
        ]);
    }

    /**
     * Récupère la durée de rétention depuis CompanySetting.
     */
    private function getRetentionMonths(): int
    {
        try {
            $raw = CompanySetting::query()
                ->where('key', 'audit_retention_months')
                ->value('value');

            if (is_string($raw) && ctype_digit($raw) && (int) $raw >= 1) {
                return (int) $raw;
            }
        } catch (\Throwable) {
            // Silencieux : valeur par défaut
        }

        return 12;
    }

    /**
     * Génère un CSV à partir des logs.
     *
     * @param  \Illuminate\Support\Collection<int, AuditLog>  $logs
     */
    private function generateCsv($logs): string
    {
        $lines = [];
        
        // En-tête
        $lines[] = implode(',', [
            'Date',
            'Acteur',
            'Action',
            'Module',
            'Ressource',
            'IP',
        ]);

        foreach ($logs as $log) {
            $actor = $log->user 
                ? trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? ''))
                : __('auth.audit_actor_system');
            
            $resource = $log->auditable_type
                ? class_basename($log->auditable_type) . '#' . ($log->auditable_id ?? '')
                : '';

            $lines[] = implode(',', [
                $log->created_at?->toIso8601String() ?? '',
                '"' . str_replace('"', '""', $actor) . '"',
                '"' . str_replace('"', '""', $log->action) . '"',
                '"' . str_replace('"', '""', $log->module ?? '') . '"',
                '"' . str_replace('"', '""', $resource) . '"',
                $log->ip_address ?? '',
            ]);
        }

        return implode("\n", $lines);
    }
}
