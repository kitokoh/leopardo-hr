<?php

declare(strict_types=1);

namespace App\Core\Tenant\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Issue #7598 (R1 de l'épique #7597) — `GET /v1/resources/{type}`.
 *
 * Le sélecteur du responsable : « quels restaurants / véhicules / caméras puis-je
 * donner ? ». C'est ce qui manquait pour qu'une assignation soit possible sans
 * connaître les identifiants par cœur.
 *
 * Deux garde-fous :
 *  - un type non déclaré au registre est refusé (`RESOURCE_TYPE_UNKNOWN`) —
 *    on n'ouvre jamais un type par défaut ;
 *  - la liste est **filtrée par ce que l'acteur peut voir** du type
 *    (`accessibleResourceIds`) : `null` = aucune restriction (principal, ou
 *    type pas encore assigné), liste vide = rien, liste = les ressources
 *    assignées au niveau demandé.
 *
 * Réservé au principal du tenant en R1 : c'est le geste d'assignation qui
 * ouvre ce catalogue (R4 élargira l'UX).
 */
class ResourceCatalogController extends Controller
{
    public function __construct(private readonly ResourceTypeRegistry $registry) {}

    public function index(Request $request, string $type): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isPrincipal()) {
            return new JsonResponse([
                'error' => 'RESOURCE_ACCESS_DENIED',
                'message' => __('errors.RESOURCE_ACCESS_DENIED'),
            ], 403);
        }

        if (! $this->registry->has($type)) {
            throw new NotFoundHttpException(__('errors.RESOURCE_TYPE_UNKNOWN'));
        }

        $only = $actor->accessibleResourceIds($type);

        return new JsonResponse([
            'data' => $this->registry->listForCompany($type, $actor->company_id, $only),
        ]);
    }

    /**
     * Issue #7601 (R4 de l'épique #7597) — la vue INVERSE : « qui a accès à
     * CETTE ressource ? » (une succursale → ses collaborateurs et leurs
     * niveaux). Même règle d'accès que le catalogue : `principal` du tenant.
     */
    public function access(Request $request, string $type, int $resourceId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isPrincipal()) {
            return new JsonResponse([
                'error' => 'RESOURCE_ACCESS_DENIED',
                'message' => __('errors.RESOURCE_ACCESS_DENIED'),
            ], 403);
        }

        if (! $this->registry->has($type)) {
            throw new NotFoundHttpException(__('errors.RESOURCE_TYPE_UNKNOWN'));
        }

        if (! $this->registry->exists($type, $resourceId, $actor->company_id)) {
            throw new NotFoundHttpException(__('errors.RESOURCE_NOT_FOUND'));
        }

        $assignments = EmployeeResourceAssignment::query()
            ->where('company_id', $actor->company_id)
            ->where('resource_type', $type)
            ->where('resource_id', $resourceId)
            ->with(['employee', 'creator'])
            ->orderBy('employee_id')
            ->get();

        $data = [];
        foreach ($assignments as $assignment) {
            $data[] = [
                'employee_id' => (int) $assignment->employee_id,
                'employee_name' => trim(($assignment->employee->first_name ?? '').' '.($assignment->employee->last_name ?? '')),
                'access_level' => (string) $assignment->access_level,
                'granted_by' => $assignment->created_by !== null ? (int) $assignment->created_by : null,
                'granted_by_name' => $assignment->creator !== null
                    ? trim(($assignment->creator->first_name ?? '').' '.($assignment->creator->last_name ?? ''))
                    : null,
                'granted_at' => $assignment->created_at?->toIso8601String(),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * Issue #7601 (R4) — rapport d'audit des accès ressource : qui avait accès
     * à quoi, quand, donné (ou retiré) par qui. Source : `audit_logs` (trait
     * `Auditable` du modèle — chaque pose/modification/révocation y écrit une
     * ligne, y compris la révocation en cascade au départ d'un collaborateur).
     * `?format=csv` produit l'export téléchargeable demandé par l'issue.
     */
    public function audit(Request $request): JsonResponse|StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isPrincipal()) {
            return new JsonResponse([
                'error' => 'RESOURCE_ACCESS_DENIED',
                'message' => __('errors.RESOURCE_ACCESS_DENIED'),
            ], 403);
        }

        $limit = max(1, min(1000, (int) $request->query('limit', 200)));

        $logs = AuditLog::query()
            ->where('company_id', $actor->company_id)
            ->where('auditable_type', (new EmployeeResourceAssignment)->getMorphClass())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($logs as $log) {
            /** @var array<string, mixed> $old */
            $old = $log->old_values ?? [];
            /** @var array<string, mixed> $new */
            $new = $log->new_values ?? [];
            $snapshot = $new + $old;

            $rows[] = [
                'at' => $log->created_at?->toIso8601String(),
                'action' => (string) $log->action,
                'actor_id' => $log->user_id !== null ? (int) $log->user_id : null,
                'employee_id' => isset($snapshot['employee_id']) ? (int) $snapshot['employee_id'] : null,
                'resource_type' => isset($snapshot['resource_type']) ? (string) $snapshot['resource_type'] : null,
                'resource_id' => isset($snapshot['resource_id']) ? (int) $snapshot['resource_id'] : null,
                'access_level' => isset($snapshot['access_level']) ? (string) $snapshot['access_level'] : null,
                'previous_level' => isset($old['access_level'], $new['access_level']) ? (string) $old['access_level'] : null,
            ];
        }

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($rows): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['at', 'action', 'actor_id', 'employee_id', 'resource_type', 'resource_id', 'access_level', 'previous_level']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['at'], $row['action'], $row['actor_id'], $row['employee_id'],
                        $row['resource_type'], $row['resource_id'], $row['access_level'], $row['previous_level'],
                    ]);
                }
                fclose($out);
            }, 'resource-access-audit.csv', ['Content-Type' => 'text/csv']);
        }

        return new JsonResponse(['data' => $rows]);
    }
}
