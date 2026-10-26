<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Application\Services\SeoIssueWorkflow;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueSeverity;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Exceptions\InvalidTransitionException;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GSI-2 (#8513) — file de traitement des anomalies SEO.
 *
 * `GET   /seo/issues`               — liste, triée par gravité puis date
 * `PATCH /seo/issues/{issue}/transition` — transition de workflow auditée
 *
 * La gravité est un `enum` ; en base c'est une chaîne. Le tri « gravité
 * d'abord » ne peut donc pas être un simple `orderBy` alphabétique —
 * `critical` passerait après `high`. On trie en PHP sur le rang de l'enum,
 * la file restant de taille humaine (une page = quelques anomalies).
 */
final class SearchIntelSeoIssueController
{
    public function __construct(
        private readonly SeoIssueWorkflow $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(array_map(
                static fn (SeoIssueStatus $s): string => $s->value,
                SeoIssueStatus::cases(),
            ))],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $query = SeoIssue::query()->with('page');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $query->actionable();
        }

        /** @var \Illuminate\Support\Collection<int, SeoIssue> $issues */
        $issues = $query->orderByDesc('detected_at')
            ->limit((int) ($validated['limit'] ?? 100))
            ->get();

        $sorted = $issues->sortBy([
            static fn (SeoIssue $a, SeoIssue $b): int => $a->severity->rank() <=> $b->severity->rank(),
            static fn (SeoIssue $a, SeoIssue $b): int => ($b->detected_at->timestamp) <=> ($a->detected_at->timestamp),
        ])->values();

        return new JsonResponse([
            'data' => $sorted->map(fn (SeoIssue $issue): array => [
                'id' => $issue->id,
                'type' => $issue->type,
                'severity' => $issue->severity->value,
                'status' => $issue->status->value,
                'status_label' => $issue->status->label(),
                'title' => $issue->title,
                'recommendation' => $issue->recommendation,
                'url' => $issue->page?->url,
                'detected_at' => $issue->detected_at->toIso8601String(),
                'allowed_transitions' => array_map(
                    static fn (SeoIssueStatus $s): string => $s->value,
                    $issue->status->allowedTransitions(),
                ),
            ])->values(),
            'meta' => [
                'total' => $sorted->count(),
            ],
        ]);
    }

    public function transition(Request $request, SeoIssue $issue): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(
                static fn (SeoIssueStatus $s): string => $s->value,
                SeoIssueStatus::cases(),
            ))],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $target = SeoIssueStatus::from($validated['status']);
        $actor = $request->user('super_admin_api');

        try {
            $updated = $this->workflow->transition(
                $issue,
                $target,
                $actor instanceof SuperAdmin ? $actor->id : null,
                $validated['reason'] ?? null,
            );
        } catch (InvalidTransitionException $exception) {
            return new JsonResponse([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                    'current_status' => $issue->status->value,
                    'allowed_transitions' => array_map(
                        static fn (SeoIssueStatus $s): string => $s->value,
                        $issue->status->allowedTransitions(),
                    ),
                ],
            ], 422);
        }

        return new JsonResponse([
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
                'status_label' => $updated->status->label(),
            ],
        ]);
    }

    /**
     * Métadonnées statiques utiles au front (libellés, gravités ordonnées).
     * Exposé ici plutôt que codé en dur côté SPA : une évolution de l'enum se
     * propage automatiquement.
     */
    public function meta(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'statuses' => array_map(
                    static fn (SeoIssueStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                    SeoIssueStatus::cases(),
                ),
                'severities' => array_map(
                    static fn (SeoIssueSeverity $s): string => $s->value,
                    SeoIssueSeverity::cases(),
                ),
                'permissions' => [
                    'view' => PlatformPermission::SearchIntelView->value,
                    'manage' => PlatformPermission::SearchIntelManage->value,
                ],
            ],
        ]);
    }
}
