<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Issue #5720 — Journal d'activités CRM du tenant (`/crm/activities`).
 *
 * La matrice de contrat (`docs/specifications/CRM_API_MATRICE_TENANT_PLATFORM.md`
 * §2.5) documente `GET` et `POST /api/v1/crm/activities` ; seule la timeline
 * par account (`/crm/accounts/{account}/timeline`) existait réellement, d'où un
 * 404 incohérent avec le reste de l'API.
 *
 * Le modèle est **append-only** : aucune route PUT/PATCH/DELETE n'est exposée
 * (la timeline est un journal, on ne réécrit pas l'histoire). `occurred_at`
 * porte la date métier, `created_at` l'horodatage d'insertion.
 *
 * RBAC : mêmes rôles que les tâches CRM (`CrmTaskPolicy::viewAny` /
 * `create` — principal, rh, marketing), via la Gate `crm.timeline` déjà
 * enregistrée. Isolation tenant : scope `BelongsToCompany` + filtre explicite
 * `company_id` (fail-closed).
 */
class CrmActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('crm.timeline');

        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(CrmActivity::TYPES)],
            'account_id' => ['sometimes', 'integer', 'min:1'],
            'contact_id' => ['sometimes', 'integer', 'min:1'],
            'lead_id' => ['sometimes', 'integer', 'min:1'],
            'opportunity_id' => ['sometimes', 'integer', 'min:1'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = CrmActivity::query()
            ->where('company_id', $actor->company_id)
            ->with('actor:id,first_name,last_name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        foreach (['type', 'account_id', 'contact_id', 'lead_id', 'opportunity_id'] as $filter) {
            if (isset($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (isset($validated['from'])) {
            $query->whereDate('occurred_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('occurred_at', '<=', $validated['to']);
        }

        $paginator = $query->paginate(
            (int) ($validated['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($validated['page'] ?? 1),
        );

        return CrmActivityResource::collection($paginator)->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('crm.timeline');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(CrmActivity::TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'opportunity_id' => ['nullable', 'integer', 'min:1'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        // Une activité doit être rattachée à au moins une entité métier, sinon
        // elle n'apparaît dans aucune timeline et n'a aucune valeur.
        $anchors = array_filter([
            $validated['account_id'] ?? null,
            $validated['contact_id'] ?? null,
            $validated['lead_id'] ?? null,
            $validated['opportunity_id'] ?? null,
        ], static fn ($value): bool => $value !== null);

        if ($anchors === []) {
            return new JsonResponse([
                'error' => 'VALIDATION_ERROR',
                'message' => 'An activity must reference at least one of account_id, contact_id, lead_id or opportunity_id.',
                'localized_message' => 'Une activité doit référencer au moins un compte, contact, prospect ou opportunité.',
            ], 422);
        }

        $activity = CrmActivity::query()->create([
            'company_id' => $actor->company_id,
            'account_id' => $validated['account_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'lead_id' => $validated['lead_id'] ?? null,
            'opportunity_id' => $validated['opportunity_id'] ?? null,
            'type' => $validated['type'],
            'subject' => $validated['subject'] ?? null,
            'description' => $validated['description'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
            'created_by' => $actor->id,
        ]);

        $activity->load('actor:id,first_name,last_name');

        return (new CrmActivityResource($activity))->response()->setStatusCode(201);
    }
}
