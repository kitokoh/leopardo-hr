<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5273 — Audit trail du module Comptabilité : qui, quoi, quand, sur les
 * documents comptables (création, envoi, paiement, annulation, avoir).
 *
 * Le endpoint global `/audit-logs` est réservé au rôle `principal` ; ici la
 * vue est scopée au module (`metadata.resource LIKE accounting.%`) et
 * ouverte aux rôles principal ET comptable (RBAC Comptabilité).
 * Lecture seule — les audit logs sont append-only.
 */
class AccountingAuditController extends Controller
{
    /**
     * GET /api/v1/accounting/audit-logs — liste paginée des événements du module.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'resource' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $prefix = (string) config('accounting.audit_resource_prefix', 'accounting.');

        $query = AuditLog::query()
            ->forCompany((string) $actor->company_id)
            ->with('user:id,first_name,last_name')
            ->where('metadata->resource', 'like', $prefix.'%');

        if (! empty($validated['resource'])) {
            $query->where('metadata->resource', $validated['resource']);
        }
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        return AuditLogResource::collection(
            $query->orderByDesc('created_at')->paginate((int) ($validated['per_page'] ?? 25))
        )->response();
    }
}
