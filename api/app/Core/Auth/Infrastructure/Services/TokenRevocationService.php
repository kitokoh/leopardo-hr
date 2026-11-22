<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Issue #9808 — révocation INSTANTANÉE et bannissement de compte.
 *
 * ## Le trou que ce service comble
 *
 * Avant, révoquer un jeton voulait dire une seule chose : supprimer sa ligne
 * dans `personal_access_tokens` (`LogoutAction::execute`, `ChangePasswordAction`,
 * `EmployeeService::archive`). Sanctum relit cette table à chaque requête, donc
 * la suppression « marche » — mais elle a trois limites structurelles :
 *
 *  1. **Pas de bannissement temporaire.** Une ligne supprimée ne revient pas ;
 *     « geler 24 h un compte compromis puis rendre l'accès » est impossible.
 *  2. **Pas de motif conservé.** `auth.token.revoked` est journalisé pour un
 *     `logout` utilisateur, mais aucune révocation ADMINISTRATIVE n'existe : on
 *     ne peut pas répondre à « qui a coupé cet accès, quand, et pourquoi ? ».
 *  3. **Une écriture base par jeton.** Révoquer tous les jetons d'une société
 *     fait N `DELETE` ; ce service fait N écritures de clés, sans verrou de
 *     table.
 *
 * ## Ce que ce service garantit
 *
 *  - `revoke()` marque un jeton ; la requête SUIVANTE est refusée, sans
 *    suppression de ligne (donc réversible, donc auditable) ;
 *  - `ban()` ferme TOUTES les sessions d'un compte, pour une durée choisie ou
 *    indéfiniment ;
 *  - le TTL d'une clé de révocation ne dépasse JAMAIS l'expiration réelle du
 *    jeton : on ne laisse pas de clé orpheline éternelle en cache.
 *
 * ## Politique de panne (décision explicite, cf. #9808)
 *
 * Le cache porte la DÉCISION DE COUPURE. Deux échecs différents :
 *
 *  - **lecture impossible** (Redis injoignable) : on REFUSE. Un cache muet ne
 *    prouve pas qu'un jeton est valide, et « on ne sait pas » ne doit pas
 *    redevenir « laissez passer ». C'est le seul choix coercitif cohérent avec
 *    l'objectif (couper un accès compromis) ;
 *  - **écriture impossible** (révocation impossible à enregistrer) : on
 *    propage l'erreur à l'appelant. Une révocation qui échoue silencieusement
 *    est pire qu'une révocation qui échoue bruyamment.
 *
 * `revocation.enabled = false` est le seul chemin de désactivation, et il est
 * réservé aux tests locaux — il est refusé en production (`forget()`), voir
 * `config/auth-revocation.php`.
 */
final class TokenRevocationService
{
    /** Préfixe des clés : `auth:revoked:token:{id}`. */
    public const TOKEN_KEY_PREFIX = 'auth:revoked:token:';

    /** Préfixe des clés de bannissement : `auth:banned:{type}:{id}`. */
    public const BAN_KEY_PREFIX = 'auth:banned:';

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_USER = 'user';

    public const TYPE_TRAVEL_CUSTOMER = 'travel_customer';

    public const TYPE_SUPER_ADMIN = 'super_admin';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Store effectif : `auth-revocation.store` s'il est défini, sinon le store
     * par défaut. Résolu à l'appel (et non au constructeur) pour que les tests
     * puissent changer `cache.default` sans reconstruire le service.
     */
    private function store(): CacheRepository
    {
        $name = config('auth-revocation.store');

        if (is_string($name) && $name !== '') {
            return $this->cache->store($name);
        }

        return $this->cache;
    }

    // ---------------------------------------------------------------------
    // Révocation de jeton
    // ---------------------------------------------------------------------

    /**
     * Révoque un jeton identifié par son `personal_access_tokens.id`.
     *
     * @param  null|string  $expiresAt  Expiration RÉELLE du jeton (`expires_at`).
     *                                  Sert à borner le TTL : jamais au-delà.
     * @param  string  $reason  Motif lisible, journalisé.
     * @param  int|null  $actorId  Auteur de la révocation (null = système).
     */
    public function revoke(int $tokenId, ?string $expiresAt, string $reason, ?int $actorId = null, ?int $companyId = null): void
    {
        $ttl = $this->ttlSeconds($expiresAt);

        if ($ttl === null) {
            // Jeton déjà expiré : rien à ajouter, Sanctum le refusera de
            // toute façon. On évite une clé qui ne servirait jamais.
            return;
        }

        $this->store()->put($this->tokenKey($tokenId), $reason, $ttl);

        Log::channel('structured')->info('auth.token.revoked', [
            'token_id' => $tokenId,
            'reason' => $reason,
            'actor_id' => $actorId,
            'company_id' => $companyId,
            'ttl_seconds' => $ttl,
        ]);

        $this->audit('auth.token.revoked', [
            'token_id' => $tokenId,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Le jeton est-il révoqué ?
     *
     * Fail-closed : si le cache est injoignable, on lève — l'appelant (hook
     * Sanctum) convertit en refus. Voir l'en-tête de classe.
     */
    public function isRevoked(int $tokenId): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->store()->get($this->tokenKey($tokenId)) !== null;
    }

    // ---------------------------------------------------------------------
    // Bannissement de compte
    // ---------------------------------------------------------------------

    /**
     * Bannit un compte, tous guards confondus.
     *
     * @param  string  $type  L'une des constantes TYPE_*.
     * @param  int  $id  Identifiant du compte dans son provider.
     * @param  CarbonImmutable|null  $until  Fin du bannissement (`null` = permanent).
     */
    public function ban(string $type, int $id, ?CarbonImmutable $until = null, ?string $reason = null, ?int $actorId = null): void
    {
        $key = $this->banKey($type, $id);
        $ttl = $until === null ? null : max(1, (int) CarbonImmutable::now()->diffInSeconds($until, false));

        // `put` avec TTL null = pas d'expiration (bannissement permanent).
        $this->store()->put($key, $reason ?? 'banned', $ttl);

        Log::channel('structured')->info('auth.account.banned', [
            'type' => $type,
            'id' => $id,
            'until' => $until?->toIso8601String(),
            'permanent' => $until === null,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);

        $this->audit('auth.account.banned', [
            'account_type' => $type,
            'account_id' => $id,
            'until' => $until?->toIso8601String(),
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
    }

    /** Lève un bannissement (le compte retrouve l'accès à la requête suivante). */
    public function unban(string $type, int $id, ?int $actorId = null): void
    {
        $this->store()->forget($this->banKey($type, $id));

        Log::channel('structured')->info('auth.account.unbanned', [
            'type' => $type,
            'id' => $id,
            'actor_id' => $actorId,
        ]);
    }

    public function isBanned(string $type, int $id): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->store()->get($this->banKey($type, $id)) !== null;
    }

    // ---------------------------------------------------------------------
    // Aides
    // ---------------------------------------------------------------------

    /**
     * Clé de cache d'un jeton.
     */
    public function tokenKey(int $tokenId): string
    {
        return $this->prefix().self::TOKEN_KEY_PREFIX.$tokenId;
    }

    /**
     * Clé de cache d'un bannissement.
     */
    public function banKey(string $type, int $id): string
    {
        return $this->prefix().self::BAN_KEY_PREFIX.$type.':'.$id;
    }

    private function prefix(): string
    {
        $prefix = config('auth-revocation.key_prefix', 'leopardo:');

        return is_string($prefix) ? $prefix : '';
    }

    /**
     * Type de compte porté par un modèle authentifiable.
     *
     * Retourne `null` pour un modèle non reconnu : l'appelant ne doit alors
     * appliquer aucune règle de bannissement (pas d'invention de type).
     */
    public function typeOf(Model $user): ?string
    {
        return match (true) {
            $user instanceof Employee => self::TYPE_EMPLOYEE,
            $user::class === 'App\\Modules\\TravelAgency\\Domain\\Models\\TravelCustomerAccount' => self::TYPE_TRAVEL_CUSTOMER,
            $user::class === 'App\\Core\\Tenant\\Domain\\Models\\SuperAdmin' => self::TYPE_SUPER_ADMIN,
            $user::class === 'App\\Core\\Auth\\Domain\\Models\\User' => self::TYPE_USER,
            default => null,
        };
    }

    /**
     * TTL en secondes borné par l'expiration réelle du jeton.
     *
     * Retourne `null` quand il n'y a rien à stocker : jeton déjà expiré, ou
     * jeton sans expiration (configuration de développement) pour lequel une
     * clé permanente serait un piège.
     */
    private function ttlSeconds(?string $expiresAt): ?int
    {
        if ($expiresAt === null) {
            // `sanctum.expiration = 0` → jetons sans échéance. On applique la
            // durée interactive par défaut comme borne haute : une clé de
            // révocation ne doit pas vivre plus longtemps qu'une session.
            $fallback = (int) config('sanctum.expiration', 720);

            return $fallback > 0 ? $fallback * 60 : null;
        }

        try {
            $expires = CarbonImmutable::parse($expiresAt);
        } catch (\Throwable) {
            return null;
        }

        $remaining = (int) CarbonImmutable::now()->diffInSeconds($expires, false);

        return $remaining > 0 ? $remaining : null;
    }

    private function enabled(): bool
    {
        return (bool) config('auth-revocation.enabled', true);
    }

    /**
     * Écrit l'entrée d'audit, best-effort.
     *
     * `AuditLog` vit dans le SCHÉMA TENANT (`audit_logs`). Une révocation peut
     * être émise depuis la plateforme (super-admin), où aucun schéma tenant
     * n'est sélectionné : la table n'existe alors pas. On ne fait pas échouer
     * une révocation — qui doit couper un accès MAINTENANT — pour un journal :
     * l'événement reste dans `structured.log`, qui est le canal d'audit
     * consultable côté plateforme.
     *
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $action, array $payload): void
    {
        try {
            AuditLog::record('auth', $action, null, null, [], $payload);
        } catch (\Throwable $e) {
            Log::channel('structured')->warning('auth.audit_write_failed', [
                'action' => $action,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
