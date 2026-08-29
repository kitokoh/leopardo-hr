<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Queries\AccountTimelineQuery;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5720 — Timeline d'activités d'un account CRM (append-only).
 *
 * Cursor pagination keyset (`before_id`, ordre id DESC). L'account est
 * résolu via le scope tenant (un account d'un autre tenant → 404).
 */
class CrmTimelineController extends Controller
{
    public function __construct(private readonly AccountTimelineQuery $accountTimelineQuery) {}

    public function index(Request $request, CrmAccount $account): JsonResponse
    {
        $this->authorize('crm.timeline');

        $limit = (int) $request->query('limit', 25);
        $beforeId = $request->query('before_id') !== null ? (int) $request->query('before_id') : null;

        $result = $this->accountTimelineQuery->execute($account, $limit, $beforeId);

        return response()->json([
            'data' => CrmActivityResource::collection($result['items']),
            'meta' => [
                'next_cursor' => $result['next_cursor'],
            ],
        ]);
    }
}
