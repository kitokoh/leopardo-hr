<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Enums\CrmMergeEntityType;
use App\Modules\CRM\Domain\Exceptions\CrmMergeException;
use App\Modules\CRM\Infrastructure\Services\CrmDeduplicationService;
use App\Modules\CRM\Interfaces\Api\V1\Requests\CrmMergeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5718 — API de déduplication et fusion SUPERVISÉE.
 *
 *  - GET  /crm/dedup/suggestions  — paires explicables (tenant-scoped)
 *  - GET  /crm/merge/preview      — diff avant fusion (aucune écriture)
 *  - POST /crm/merge              — fusion confirmée (principal uniquement)
 */
class CrmDedupController extends Controller
{
    public function __construct(private readonly CrmDeduplicationService $service)
    {
    }

    public function suggestions(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewSuggestions', \App\Modules\CRM\Domain\Models\CrmAccount::class)) {
            abort(403);
        }

        $entity = $this->entity($request->input('entity', 'accounts'));
        $limit = (int) $request->input('limit', CrmDeduplicationService::DEFAULT_SUGGESTIONS);

        return new JsonResponse(['data' => $this->service->suggestions($entity, (string) $actor->company_id, $limit)]);
    }

    public function preview(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('preview', \App\Modules\CRM\Domain\Models\CrmAccount::class)) {
            abort(403);
        }

        try {
            $preview = $this->service->preview(
                $this->entity($request->input('entity', 'accounts')),
                (int) $request->input('winner_id'),
                (int) $request->input('loser_id'),
                (string) $actor->company_id,
            );
        } catch (CrmMergeException $e) {
            return $this->mergeError($e);
        }

        return new JsonResponse(['data' => $preview]);
    }

    public function merge(CrmMergeRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('merge', \App\Modules\CRM\Domain\Models\CrmAccount::class)) {
            abort(403);
        }

        try {
            $result = $this->service->merge(
                CrmMergeEntityType::from($request->input('entity')),
                (int) $request->input('winner_id'),
                (int) $request->input('loser_id'),
                $actor,
            );
        } catch (CrmMergeException $e) {
            return $this->mergeError($e);
        }

        return new JsonResponse(['data' => $result]);
    }

    private function entity(string $value): CrmMergeEntityType
    {
        $entity = CrmMergeEntityType::tryFrom($value);

        if ($entity === null) {
            abort(422, __('crm.merge.unknown_entity'));
        }

        return $entity;
    }

    private function mergeError(CrmMergeException $e): JsonResponse
    {
        return new JsonResponse([
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);
    }
}
