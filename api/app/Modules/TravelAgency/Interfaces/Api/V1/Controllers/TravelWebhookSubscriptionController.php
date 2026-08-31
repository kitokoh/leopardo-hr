<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelWebhookSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * TRAVEL-806 (#6097) — Abonnements webhooks transporteurs (CRUD).
 *
 * Le secret de signature est chiffré au repos (Crypt::encryptString) —
 * les réponses n'exposent que son préfixe de hash (jamais le secret).
 * Un abonnement par (company, carrier) : la création d'un second
 * abonnement pour le même transporteur met à jour le premier (upsert).
 */
class TravelWebhookSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelWebhookSubscription::class)) {
            abort(403);
        }

        $subscriptions = TravelWebhookSubscription::query()
            ->with('carrier')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TravelWebhookSubscription $s): array => $this->present($s));

        return response()->json(['data' => $subscriptions]);
    }

    public function store(StoreTravelWebhookSubscriptionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelWebhookSubscription::class)) {
            abort(403);
        }

        $subscription = TravelWebhookSubscription::query()->updateOrCreate(
            ['company_id' => (string) $actor->company_id, 'carrier_id' => (int) $request->validated('carrier_id')],
            [
                'url' => $request->validated('url'),
                'secret_encrypted' => Crypt::encryptString((string) $request->validated('secret')),
                'events' => $request->validated('events'),
                'active' => (bool) ($request->validated('active') ?? true),
                'created_by_user_id' => (int) $actor->id,
            ],
        );

        return response()->json(['data' => $this->present($subscription->refresh())], 201);
    }

    public function destroy(Request $request, int $subscription): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var TravelWebhookSubscription|null $model */
        $model = TravelWebhookSubscription::query()->find($subscription);

        if ($model === null || $model->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $model)) {
            abort(403);
        }

        $model->delete();

        return response()->json(null, 204);
    }

    /**
     * Présentation API — jamais le secret, seulement son préfixe de hash.
     *
     * @return array<string, mixed>
     */
    private function present(TravelWebhookSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'carrier_id' => $subscription->carrier_id,
            'carrier_code' => $subscription->carrier?->code,
            'url' => $subscription->url,
            'secret_prefix' => $subscription->secretPrefix(),
            'events' => $subscription->events,
            'active' => $subscription->active,
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}
