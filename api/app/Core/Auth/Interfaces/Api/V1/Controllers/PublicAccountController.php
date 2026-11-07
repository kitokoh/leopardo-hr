<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Application\Actions\RefreshTokenAction;
use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * COULOIR 16 — M2 (#9104) · Cycle de vie du compte public.
 *
 * Trois operations que le contrat `PUBLIC_ACCOUNT_API_CONTRACT.md` §2.4-2.6
 * exige et qui n'avaient aucune route sous `user_api` :
 *
 *  - `POST /user/refresh`     — rotation du jeton (l'ancien est revoque) ;
 *  - `POST /user/logout-all`  — revocation de TOUS les appareils ;
 *  - `DELETE /user/account`   — suppression differee de 30 jours, reversible.
 *
 * ## Pourquoi la suppression est differee et non immediate
 *
 * Une suppression immediate est irreversible : un jeton vole, une erreur de
 * manipulation ou un client qui se trompe d'appareil detruirait un compte sans
 * retour. Le contrat impose donc 30 jours de reversibilite, avec
 * **anonymisation** et non effacement brutal : les donnees de facturation et de
 * reservation sont conservees (obligation legale), seuls les champs personnels
 * sont purges. Un `users` lie a un `employees` n'est **jamais** supprime — seul
 * le lien est rompu.
 *
 * ## Re-authentification obligatoire
 *
 * `DELETE /user/account` exige le mot de passe ET le mot de confirmation. Un
 * jeton vole ne doit pas pouvoir supprimer un compte : c'est la seule operation
 * du cycle de vie qui est destructive, elle est donc la seule a re-prouver
 * l'identite.
 */
class PublicAccountController extends Controller
{
    /** Delai de reversibilite avant purge effective. */
    private const PURGE_DELAY_DAYS = 30;

    /** Mot de confirmation attendu (localise cote client, verifie cote serveur). */
    private const CONFIRMATION_WORD = 'SUPPRIMER';

    public function __construct(
        private readonly RefreshTokenAction $refreshTokenAction,
    ) {}

    /**
     * POST /api/v1/user/refresh
     *
     * Rotation systematique : `RefreshTokenAction` cree le nouveau jeton et
     * supprime l'ancien dans la meme transaction (verrou pessimiste, #5581).
     * Fenetre de grace 0 — pas de reutilisation.
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        $result = $this->refreshTokenAction->execute($user);

        return new JsonResponse($result);
    }

    /**
     * POST /api/v1/user/logout-all
     *
     * Revoque TOUS les jetons de l'utilisateur, y compris celui qui porte la
     * requete. C'est le geste « appareil perdu » du contrat (cas 8 de la
     * matrice de connexion).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        $revoked = $user->tokens()->delete();

        Log::info('auth.public_logout_all', [
            'user_id' => $user->id,
            'revoked' => $revoked,
        ]);

        return new JsonResponse(null, 204);
    }

    /**
     * DELETE /api/v1/user/account
     *
     * Suppression **differee** : le compte est marque, la purge est planifiee a
     * 30 jours, et l'utilisateur peut annuler jusque-la. Aucune donnee n'est
     * detruite dans cette requete.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        // Re-authentification : un jeton vole ne doit pas pouvoir supprimer un
        // compte. Un compte sans mot de passe (OTP seul) ne peut pas passer par
        // ici — il doit d'abord en definir un, ce qui est le comportement sur.
        if (! $user->password_hash || ! Hash::check((string) $validated['password'], $user->password_hash)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'INVALID_CREDENTIALS',
                'localized_message' => __('auth.password_incorrect'),
            ], 401);
        }

        if (strtoupper(trim((string) $validated['confirmation'])) !== self::CONFIRMATION_WORD) {
            return new JsonResponse([
                'success' => false,
                'error' => 'CONFIRMATION_REQUIRED',
                'localized_message' => __('auth.public_account_deletion_confirmation_required'),
            ], 422);
        }

        $purgeAt = now()->addDays(self::PURGE_DELAY_DAYS);

        DB::transaction(function () use ($user, $purgeAt): void {
            $user->forceFill([
                'deletion_requested_at' => now(),
                'purge_scheduled_at' => $purgeAt,
            ])->save();

            // Tous les appareils sont deconnectes immediatement : un compte en
            // cours de suppression ne doit plus etre utilisable.
            $user->tokens()->delete();
        });

        Log::info('auth.public_account_deletion_requested', [
            'user_id' => $user->id,
            'purge_scheduled_at' => $purgeAt->toIso8601String(),
        ]);

        return new JsonResponse([
            'status' => 'pending',
            'purge_scheduled_at' => $purgeAt->toIso8601String(),
        ], 202);
    }
}
