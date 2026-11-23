<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Contracts\TokenRevocationContract;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Interfaces\Api\V1\Requests\BanPlatformAccountRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #9815 — bannissement de compte exposé côté API plateforme.
 *
 * ## Pourquoi cette surface existe
 *
 * #9808 a livré `TokenRevocationService` et la commande `auth:ban-account`.
 * Le service est solide, mais le seul chemin pour s'en servir était un
 * terminal : la console plateforme ne pouvait pas couper l'accès d'un compte
 * compromis. Le docblock de `BanAccountCommand` annonçait déjà ce contrôleur
 * — il n'existait pas. C'est ce faux positif que cette classe corrige.
 *
 * ## Ce que ce contrôleur ne fait PAS
 *
 * Il ne réimplémente aucune règle : la décision (clé de cache, TTL borné par
 * l'expiration réelle du jeton, audit, politique de panne) reste dans
 * `TokenRevocationService`. Ici on valide la demande et on la traduit en
 * réponse HTTP. Une seconde implémentation aurait été le vrai risque : deux
 * définitions de « banni » qui divergent.
 *
 * ## Bannir n'est pas suspendre
 *
 * `status = suspended` est un état MÉTIER, porté par la base et vérifié par
 * `AccountStatusGuard` (#9809). Un bannissement est un geste de SÉCURITÉ :
 * il ne touche à aucune donnée, il refuse les jetons. Les deux se cumulent
 * sans se contredire — c'est pourquoi la permission est `accounts.ban` et
 * non `users.manage`.
 *
 * ## Routes
 *
 *   POST   /platform/accounts/{type}/{id}/ban   → poser un bannissement
 *   DELETE /platform/accounts/{type}/{id}/ban   → le lever
 *   GET    /platform/accounts/{type}/{id}/ban   → consulter l'état
 *
 * Toutes sous le groupe `platform` : `auth:super_admin_api`, `platform.mfa`,
 * `throttle:platform-sensitive`, plus `platform.permission:accounts.ban`.
 */
class PlatformAccountBanController extends Controller
{
    /**
     * Types acceptés — la liste vient du service, jamais d'une copie locale.
     *
     * @var list<string>
     */
    private const TYPES = [
        TokenRevocationContract::TYPE_EMPLOYEE,
        TokenRevocationContract::TYPE_USER,
        TokenRevocationContract::TYPE_TRAVEL_CUSTOMER,
        TokenRevocationContract::TYPE_SUPER_ADMIN,
    ];

    public function __construct(
        private readonly TokenRevocationContract $revocations,
    ) {}

    /**
     * Pose un bannissement.
     *
     * `hours` absent (ou `null`) = bannissement permanent. `hours` présent =
     * bannissement temporaire qui expire tout seul, sans intervention.
     */
    public function ban(BanPlatformAccountRequest $request, string $type, string $id): JsonResponse
    {
        $this->assertType($type);
        $accountId = $this->accountId($id);

        $validated = $request->validated();

        $hours = $validated['hours'] ?? null;
        $until = is_int($hours) ? CarbonImmutable::now()->addHours($hours) : null;
        $reason = $validated['reason'] ?? null;

        $this->revocations->ban(
            $type,
            $accountId,
            $until,
            is_string($reason) && $reason !== '' ? $reason : null,
            $this->actorId($request),
        );

        return new JsonResponse([
            'data' => $this->state($type, $accountId),
        ], 201);
    }

    /**
     * Lève un bannissement.
     *
     * Idempotent : lever un bannissement absent réussit et laisse le compte
     * dans son état normal. Un 404 obligerait l'appelant à interroger l'état
     * avant d'agir, pour un geste qui est déjà sans effet.
     */
    public function unban(Request $request, string $type, string $id): JsonResponse
    {
        $this->assertType($type);
        $accountId = $this->accountId($id);

        $this->revocations->unban($type, $accountId, $this->actorId($request));

        return new JsonResponse([
            'data' => $this->state($type, $accountId),
        ]);
    }

    /**
     * Consulte l'état de bannissement.
     *
     * Ne révèle rien du compte lui-même (ni nom, ni e-mail) : seulement la
     * décision de coupure. Un écran de support n'a pas besoin de davantage
     * pour répondre à « cet accès est-il coupé, et jusqu'à quand ? ».
     */
    public function show(string $type, string $id): JsonResponse
    {
        $this->assertType($type);
        $accountId = $this->accountId($id);

        return new JsonResponse([
            'data' => $this->state($type, $accountId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(string $type, int $id): array
    {
        $banned = $this->revocations->isBanned($type, $id);

        return [
            'account_type' => $type,
            'account_id' => $id,
            'banned' => $banned,
            // Le motif n'est pas relu depuis le cache : `isBanned()` ne renvoie
            // qu'un booléen, et exposer la valeur brute ferait fuiter un texte
            // interne. L'historique complet vit dans `structured.log` et dans
            // `audit_logs` (`auth.account.banned` / `auth.account.unbanned`).
            'reason_recorded' => $banned,
        ];
    }

    /**
     * Refuse un type inconnu AVANT toute écriture.
     *
     * Fail-closed : un type non reconnu ne doit pas créer une clé de cache
     * fantôme (`auth:banned:typo:42`) qui ne serait jamais relue par le guard.
     */
    private function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            abort(422, sprintf(
                'Type de compte inconnu : %s. Attendu : %s.',
                $type,
                implode('|', self::TYPES),
            ));
        }
    }

    private function accountId(string $id): int
    {
        if (! ctype_digit($id) || (int) $id <= 0) {
            abort(422, 'Identifiant de compte invalide.');
        }

        return (int) $id;
    }

    private function actorId(Request $request): ?int
    {
        $actor = $request->user('super_admin_api');

        return $actor instanceof SuperAdmin ? (int) $actor->getKey() : null;
    }
}
