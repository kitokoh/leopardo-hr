<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Services\PublicNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COULOIR 16 — M3-bis (#9142) · Boîte de notifications du client public.
 *
 * `auth:user_api` obligatoire : le client est un `users.id` (identité
 * plateforme), pas un employé. Aucun middleware `tenant` — un client reçoit
 * des notifications de plusieurs commerçants, et le tenant est résolu côté
 * serveur depuis la référence.
 *
 * ## 404 uniforme, jamais 403
 *
 * Marquer comme lue une notification qui ne m'appartient pas renvoie 404, pas
 * 403 : un 403 confirmerait l'existence d'une notification qui ne me concerne
 * pas, et permettrait d'énumérer les identifiants. Même arbitrage que
 * `PublicActivityController` sur les favoris.
 */
class PublicNotificationController extends Controller
{
    public function __construct(private readonly PublicNotificationService $notifications) {}

    /**
     * GET /api/v1/user/notifications — boîte de réception du client.
     *
     * `meta.unread` alimente la pastille de l'onglet sans second appel.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $limit = (int) $request->integer('per_page', PublicNotificationService::MAX_NOTIFICATIONS);

        return new JsonResponse([
            'data' => $this->notifications->forClient($client, $limit)->values(),
            'meta' => [
                'unread' => $this->notifications->unreadCount($client),
            ],
        ]);
    }

    /**
     * POST /api/v1/user/notifications/{notification}/read — marquer comme lue.
     */
    public function markRead(Request $request, int $notification): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        if (! $this->notifications->markRead($client, $notification)) {
            return new JsonResponse(['error' => 'NOTIFICATION_NOT_FOUND'], 404);
        }

        return new JsonResponse([
            'data' => ['unread' => $this->notifications->unreadCount($client)],
        ]);
    }

    /**
     * POST /api/v1/user/notifications/read-all — tout marquer comme lu.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $updated = $this->notifications->markAllRead($client);

        return new JsonResponse([
            'data' => ['updated' => $updated, 'unread' => 0],
        ]);
    }
}
