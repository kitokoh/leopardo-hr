<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * COULOIR 16 — M2 (#9104) · Jetons d'appareil du grand public (push).
 *
 * ## Pourquoi une table PUBLIQUE et pas `device_tokens`
 *
 * `device_tokens` vit en schema **tenant** : il appartient a une entreprise et
 * sert ses employes. Un client du canal public converse avec **plusieurs**
 * entreprises et n'appartient a aucune — il ne peut donc pas avoir de ligne
 * dans une table tenant. `public_device_tokens` reprend le patron de
 * `public_conversation_index` : identifiants et metadonnees de transport,
 * aucun contenu metier.
 *
 * ## Ce que ce controleur ne fait pas
 *
 * Il n'envoie **aucune** notification : il enregistre et supprime des jetons.
 * L'envoi (FCM/APNs) est un autre lot ; ici on prepare le terrain sans
 * franchir aucune porte (aucune depense, aucun compte de developpeur).
 *
 * Le `user_id` vient TOUJOURS du jeton authentifie (guard `user_api`), jamais
 * du corps de la requete : un client ne peut pas enregistrer un appareil pour
 * quelqu'un d'autre.
 */
class PublicDeviceTokenController extends Controller
{
    private const PLATFORMS = ['ios', 'android', 'web', 'unknown'];

    /**
     * POST /api/v1/user/device-tokens
     *
     * Idempotent : le meme jeton re-enregistre met a jour sa ligne (le jeton
     * est unique en base) au lieu d'en creer une seconde. Un appareil qui
     * change de proprietaire est **reaffecte** au nouveau compte — sinon
     * l'ancien proprietaire continuerait de recevoir les notifications du
     * nouveau.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:'.implode(',', self::PLATFORMS)],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var array{token: string, platform?: string|null, device_name?: string|null} $validated */
        /** @var User $user */
        $user = $request->user('user_api');

        $token = (string) $validated['token'];

        DB::table('public_device_tokens')->updateOrInsert(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? 'unknown',
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return new JsonResponse(['status' => 'registered'], 201);
    }

    /**
     * DELETE /api/v1/user/device-tokens
     *
     * Supprime le jeton de CET utilisateur. Un jeton appartenant a un autre
     * compte n'est pas supprimable : la clause `user_id` est dans la requete,
     * pas dans une verification prealable — un client ne peut donc pas
     * desenregistrer l'appareil d'autrui, et l'operation reste idempotente.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        DB::table('public_device_tokens')
            ->where('token', (string) $validated['token'])
            ->where('user_id', $user->id)
            ->delete();

        return new JsonResponse(null, 204);
    }
}
