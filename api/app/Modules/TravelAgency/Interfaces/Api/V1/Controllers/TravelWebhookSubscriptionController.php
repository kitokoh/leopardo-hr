<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSecretService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelWebhookSubscriptionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelWebhookSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TRAVEL-806 (#6097) — Abonnements webhook transporteurs (CRUD tenant-scoped).
 *
 * Le secret HMAC est chiffré au repos et n'est renvoyé qu'UNE fois à la
 * création (champ `secret` de la réponse) ; les lectures suivantes n'exposent
 * que `has_secret`.
 */
class TravelWebhookSubscriptionController extends Controller
{
    public function __construct(private readonly TravelWebhookSecretService $secretService) {}

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

    /**
     * Crée (ou remplace) l'abonnement webhook d'un transporteur.
     *
     * #8396 — trois défauts corrigés ici, tous mesurés par la suite Travel :
     *
     *  1. **`name` était requis côté serveur** alors que le contrat documenté et
     *     les clients n'envoient que `carrier_id`/`url`/`secret`/`events` →
     *     toute création conforme partait en **422**. Le libellé est désormais
     *     dérivé de l'hôte de l'URL quand il est absent.
     *  2. **Un second abonnement pour le même transporteur créait une seconde
     *     ligne** au lieu de mettre à jour l'existante : deux endpoints pour un
     *     même transporteur délivrent chaque événement **deux fois**. Un
     *     transporteur a UN endpoint : la création est un upsert par
     *     `(company_id, carrier_id)`.
     *  3. **Le secret HMAC en clair était renvoyé dans `data.secret`**, alors
     *     que la classe promet « chiffré au repos, jamais ré-exposé » et que
     *     `payload()` expose déjà `has_secret`. Le secret n'est plus renvoyé du
     *     tout ; un préfixe stable (`secret_prefix`, dérivé du hash) permet au
     *     client de vérifier qu'il détient le bon secret sans le transporter.
     */
    public function store(StoreTravelWebhookSubscriptionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelWebhookSubscription::class)) {
            abort(403);
        }

        $url = trim((string) $request->validated('url'));
        $carrierId = $request->validated('carrier_id');

        $existing = TravelWebhookSubscription::query()
            ->where('company_id', $actor->company_id)
            ->when(
                $carrierId !== null,
                fn ($query) => $query->where('carrier_id', $carrierId),
                fn ($query) => $query->where('url', $url),
            )
            ->first();

        $subscription = $existing instanceof TravelWebhookSubscription
            ? $existing
            : new TravelWebhookSubscription(['company_id' => $actor->company_id]);

        $explicitName = $request->validated('name');

        $subscription->fill([
            'carrier_id' => $carrierId,
            'name' => is_string($explicitName) && trim($explicitName) !== ''
                ? trim($explicitName)
                : self::deriveName($url),
            'url' => $url,
            'events' => $request->validated('events'),
            'active' => (bool) ($request->validated('active') ?? true),
        ]);

        $plainSecret = $request->validated('secret');
        $issuedSecret = null;

        if (! is_string($plainSecret) || $plainSecret === '') {
            // Secret client inchangé lors d'un upsert : n'en régénérer un que
            // s'il n'y en a pas encore (sinon on casserait la vérification HMAC
            // du transporteur à chaque `POST`, sans qu'il puisse le savoir).
            if (! $existing instanceof TravelWebhookSubscription || $subscription->secret_encrypted === '') {
                $issuedSecret = Str::random(40);
            }
        } else {
            $issuedSecret = $plainSecret;
        }

        if ($issuedSecret !== null) {
            $this->secretService->set($subscription, $issuedSecret);
        }

        $subscription->save();
        $subscription->refresh();

        $data = $this->payload($subscription) + [
            'secret_prefix' => $this->secretService->prefix($subscription),
        ];

        if ($issuedSecret !== null) {
            $data['has_secret'] = true;
        }

        // Un secret GÉNÉRÉ par le serveur doit être transmis une fois, sinon le
        // transporteur ne peut jamais vérifier la signature. Un secret fourni
        // par le client n'est jamais ré-émis : il le connaît déjà, et le
        // renvoyer le ferait transiter par un corps de réponse journalisable.
        if ($issuedSecret !== null && $plainSecret === null) {
            $data['secret'] = $issuedSecret;
        }

        return response()->json(['data' => $data], 201);
    }

    /**
     * Libellé lisible dérivé de l'hôte de l'URL de livraison.
     *
     * Un `name` explicite reste prioritaire ; ceci n'est qu'un défaut pour que
     * le champ reste exploitable en console sans imposer un aller-retour au
     * client (#8396).
     */
    private static function deriveName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'Webhook transporteur';
        }

        return Str::limit($host, 120, '');
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
}
