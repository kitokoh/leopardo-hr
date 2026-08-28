<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmActivityRequest;
use App\Http\Resources\Api\V1\CrmActivityResource;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5711 — Timeline CRM client (tenant).
 *
 * **Append-only** : seuls index (lecture paginée) et store (insertion)
 * existent — aucune route de mutation des entrées de timeline.
 */
class CrmActivityController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmActivity::class);
        $this->rejectUnknownQueryKeys($request, [
            'per_page', 'sort_by', 'sort_dir',
            'account_id', 'contact_id', 'lead_id', 'opportunity_id', 'type',
        ]);

        $query = CrmActivity::query();

        foreach (['account_id', 'contact_id', 'lead_id', 'opportunity_id'] as $relationColumn) {
            if ($request->filled($relationColumn)) {
                $query->where($relationColumn, (int) $request->input($relationColumn));
            }
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $query = $this->applySort($query, $request, [
            'occurred_at' => 'occurred_at',
            'created_at' => 'created_at',
        ]);

        return CrmActivityResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmActivityRequest $request): JsonResponse
    {
        $this->authorize('create', CrmActivity::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $activity = CrmActivity::query()->create([
            'company_id' => $actor->company_id,
            'account_id' => $request->validated('account_id'),
            'contact_id' => $request->validated('contact_id'),
            'lead_id' => $request->validated('lead_id'),
            'opportunity_id' => $request->validated('opportunity_id'),
            'type' => $request->validated('type'),
            'subject' => $request->validated('subject'),
            'description' => $request->validated('description'),
            'occurred_at' => $request->validated('occurred_at') ?? now(),
            'created_by' => $actor->id,
        ]);

        return new JsonResponse(['data' => new CrmActivityResource($activity)], 201);
    }
}
