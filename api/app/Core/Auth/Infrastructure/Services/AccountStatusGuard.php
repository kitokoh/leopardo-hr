<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issue #9809 — le statut du compte est vérifié à CHAQUE requête.
 *
 * ## Le trou
 *
 * Le statut était contrôlé au LOGIN (`AuthService:206`, `UserAuthService:53`,
 * `PlatformAuthController:150`) et, pour les routes tenant, à chaque requête
 * (`TenantMiddleware:101-114`). Ailleurs, non :
 *
 *  - compte public `user_api` : aucun contrôle par requête ;
 *  - client travel `travel_customer` : ni au login (pas de colonne), ni après ;
 *  - super-admin plateforme : `SuperAdmin::isPlatformActive()` existait mais
 *    n'était appelée NULLE PART.
 *
 * Conséquence : suspendre un compte ne coupait pas sa session en cours. Le
 * jeton restait valide jusqu'à son expiration (12 h).
 *
 * ## Ce que ce service fait
 *
 * Une seule règle, appliquée au porteur d'un jeton : le compte doit être
 * `active`. Elle est branchée dans le hook Sanctum
 * (`Sanctum::authenticateAccessTokensUsing`), donc elle couvre TOUS les guards
 * à jeton d'un coup — employé, public, travel, super-admin, mobile, kiosque —
 * sans toucher au guard lui-même.
 *
 * ## Ce qu'il ne fait pas
 *
 * Il ne remplace pas `TenantMiddleware` : celui-ci vérifie en plus le statut
 * de la SOCIÉTÉ et pose le contexte tenant. Les deux se complètent ; aucun
 * n'est redondant.
 */
final class AccountStatusGuard
{
    public function __construct(
        private readonly TokenRevocationService $revocations,
    ) {}

    /**
     * Le jeton présenté est-il encore utilisable ?
     *
     * Retourne `false` (→ Sanctum répond 401) si :
     *  - le jeton est dans la liste de révocation (#9808) ;
     *  - le compte porteur est banni (#9808) ;
     *  - le compte porteur n'est plus `active` (#9809).
     *
     * Fail-closed : une erreur de lecture du cache lève, et l'appelant refuse.
     */
    public function tokenIsUsable(PersonalAccessToken $token): bool
    {
        if ($this->revocations->isRevoked((int) $token->id)) {
            Log::channel('structured')->warning('auth.token_rejected_revoked', [
                'token_id' => $token->id,
            ]);

            return false;
        }

        $user = $token->tokenable;

        if (! $user instanceof Model) {
            return false;
        }

        $type = $this->revocations->typeOf($user);

        if ($type !== null && $this->revocations->isBanned($type, (int) $user->getKey())) {
            Log::channel('structured')->warning('auth.token_rejected_banned', [
                'account_type' => $type,
                'account_id' => $user->getKey(),
            ]);

            return false;
        }

        return $this->accountIsActive($user);
    }

    /**
     * Variante utilisee par le hook Sanctum des requetes HTTP.
     *
     * ## Pourquoi elle differe de `tokenIsUsable()`
     *
     * `tokenIsUsable()` refuse tout compte non `active` (#9809). Appliquee au
     * hook Sanctum, cette regle repond **401** AVANT que le middleware de la
     * route ne s'execute — et pour un employe, c'est le middleware qui porte
     * le contrat d'erreur attendu par les clients : `TenantMiddleware` et
     * `AITenantInjector` repondent **403 `EMPLOYEE_ARCHIVED` /
     * `EMPLOYEE_SUSPENDED`** (contrat verrouille par `AuthMeLogoutTest`,
     * `AuthenticatedGuardrailsTest` et `TenantScopeFailClosedTest`).
     *
     * Le 401 du hook gagnait donc la course et cassait ce contrat : un
     * employe archive recevait `401 UNAUTHENTICATED` au lieu de
     * `403 EMPLOYEE_ARCHIVED`, et le front le renvoyait a l'ecran de
     * connexion au lieu d'afficher le motif.
     *
     * ## Ce qui est conserve ici
     *
     *  - la REVOCATION de jeton (#9808) : aucun middleware ne la verifie ;
     *  - le BANNISSEMENT de compte (#9808) : idem ;
     *  - le statut des comptes qui ne sont PAS des `Employee` (super-admin
     *    plateforme, compte public `user_api`, client travel) : c'est le trou
     *    reel que #9809 a comble, et aucun `TenantMiddleware` ne les couvre.
     *
     * Pour un `Employee`, le statut reste verifie a chaque requete — par le
     * middleware de la route, avec le bon code HTTP. L'invariant « un compte
     * archive n'a plus d'acces » est donc preserve ; seul le code de refus
     * change, et il redevient celui que les clients attendent.
     */
    public function tokenIsUsableForRequest(PersonalAccessToken $token): bool
    {
        if ($this->revocations->isRevoked((int) $token->id)) {
            Log::channel('structured')->warning('auth.token_rejected_revoked', [
                'token_id' => $token->id,
            ]);

            return false;
        }

        $user = $token->tokenable;

        if (! $user instanceof Model) {
            return false;
        }

        $type = $this->revocations->typeOf($user);

        if ($type !== null && $this->revocations->isBanned($type, (int) $user->getKey())) {
            Log::channel('structured')->warning('auth.token_rejected_banned', [
                'account_type' => $type,
                'account_id' => $user->getKey(),
            ]);

            return false;
        }

        // Employe : statut tranche par `TenantMiddleware` / `AITenantInjector`
        // (403 EMPLOYEE_ARCHIVED / EMPLOYEE_SUSPENDED). Toutes les routes
        // `auth:sanctum` sont couvertes par l'un des deux — verifie sur
        // `route:list` (2025 routes, 0 non couverte).
        if ($user instanceof Employee) {
            return true;
        }

        return $this->accountIsActive($user);
    }

    /**
     * Le compte est-il actif ?
     *
     * Chaque modèle porte son propre vocabulaire de statut ; on ne l'invente
     * pas. Un modèle sans notion de statut est considéré actif (aucune
     * régression pour les surfaces qui n'en ont pas).
     */
    public function accountIsActive(Model $user): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isPlatformActive();
        }

        if ($user instanceof Employee || $user instanceof User) {
            return $this->statusIsActive($user);
        }

        // Client travel : la colonne `status` a été ajoutée par #9809. Tant
        // qu'elle n'existe pas sur une base non migrée, on ne bloque pas.
        //
        // #9859 — `method_exists($user, 'getAttribute')` était TOUJOURS vrai
        // (`Model` définit `getAttribute`) : le test ne prouvait rien et
        // Larastan le signalait (`function.alreadyNarrowedType`). La vraie
        // question n'est pas « la méthode existe-t-elle » mais « la colonne
        // porte-t-elle une valeur » — `getAttribute` rend `null` quand elle
        // n'existe pas, ce qui est exactement le cas à couvrir.
        if ($user->getAttribute('status') !== null) {
            return $this->statusIsActive($user);
        }

        return true;
    }

    private function statusIsActive(Model $user): bool
    {
        $status = $user->getAttribute('status');

        // `null` = colonne absente ou non renseignée : on ne bloque pas un
        // compte dont le statut n'est pas exprimé (comportement historique).
        if ($status === null) {
            return true;
        }

        return $status === 'active';
    }
}
