<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelWebhookSubscriptionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelWebhookSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSecretService;

/**
 * TRAVEL-806 (#6097) — Abonnements webhook transporteurs (CRUD tenant-scoped).
 *
 * Le secret HMAC est chiffré au repos et n'est renvoyé qu'UNE fois à la
 * création (champ `secret` de la réponse) ; les lectures suivantes n'exposent
 * que `has_secret`.
 */
class TravelWebhookSubscriptionController extends Controller
{
    public function __construct(private readonly TravelWebhookSecretService $secretService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelWebhookSubscription::class)) {
            abort(403);
        }

        $subscriptions = TravelWebhookSubscription::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TravelWebhookSubscription $s) => $this->payload($s));

        return response()->json(['data' => $subscriptions]);
    }

    public function show(Request $request, TravelWebhookSubscription $travelWebhookSubscription): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $travelWebhookSubscription)) {
            abort(404);
        }

        return response()->json(['data' => $this->payload($travelWebhookSubscription)]);
    }

    public function store(StoreTravelWebhookSubscriptionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelWebhookSubscription::class)) {
            abort(403);
        }

        $plainSecret = $request->validated('secret') ?? Str::random(40);

        $subscription = new TravelWebhookSubscription([
            'company_id' => $actor->company_id,
            'carrier_id' => $request->validated('carrier_id'),
            'name' => trim((string) $request->validated('name')),
            'url' => trim((string) $request->validated('url')),
            'events' => $request->validated('events'),
            'active' => (bool) ($request->validated('active') ?? true),
        ]);
        $this->secretService->set($subscription, $plainSecret);
        $subscription->save();

        return response()->json([
            'data' => $this->payload($subscription) + ['secret' => $plainSecret],
        ], 201);
    }

    public function update(UpdateTravelWebhookSubscriptionRequest $request, TravelWebhookSubscription $travelWebhookSubscription): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelWebhookSubscription)) {
            abort(404);
        }

        $travelWebhookSubscription->fill($request->validated())->save();

        return response()->json(['data' => $this->payload($travelWebhookSubscription)]);
    }

    public function destroy(Request $request, TravelWebhookSubscription $travelWebhookSubscription): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('delete', $travelWebhookSubscription)) {
            abort(404);
        }

        $travelWebhookSubscription->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function payload(TravelWebhookSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'name' => $subscription->name,
            'url' => $subscription->url,
            'carrier_id' => $subscription->carrier_id,
            'events' => $subscription->events ?? [],
            'active' => $subscription->active,
            'has_secret' => $subscription->secret_encrypted !== '',
            'created_at' => $subscription->created_at?->toIso8601String(),
            'updated_at' => $subscription->updated_at?->toIso8601String(),
        ];
    }

    private function present(TravelWebhookSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'carrier_id' => $subscription->carrier_id,
            'carrier_code' => $subscription->carrier?->code,
            'url' => $subscription->url,
            'secret_prefix' => $this->secretService->prefix($subscription),
            'events' => $subscription->events,
            'active' => $subscription->active,
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}