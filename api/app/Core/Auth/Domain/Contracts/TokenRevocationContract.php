<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Contracts;

use Carbon\CarbonImmutable;

/**
 * Contrat de revocation de jeton et de bannissement de compte (#9808, #9852).
 *
 * **Pourquoi ce contrat existe.** `TokenRevocationService` vit dans
 * `Core/Auth/Infrastructure/Services` : c'est une classe CONCRETE
 * d'infrastructure. La garde d'architecture
 * (`IdentityLayerBoundariesArchitectureTest`, ecart I-3 de #8717) interdit aux
 * `Modules/*` d'importer une classe concrete de `Core` : ils ne peuvent
 * dependre que de contrats. `PlatformAccountBanController` (Modules/Platform)
 * violait cette regle en type-hintant le service directement — le test
 * d'architecture etait rouge sur `main`.
 *
 * **Ce que le contrat porte.** Uniquement la surface dont un appelant a
 * besoin pour couper un acces : poser un bannissement, le lever, le
 * consulter. Les details d'implementation (cle de cache, TTL borne par
 * l'expiration reelle du jeton, politique de panne fail-closed, ecriture
 * d'audit) restent dans le service — le contrat ne les expose pas et ne les
 * fige pas.
 *
 * **Les constantes `TYPE_*` sont ici, pas dans le service.** Un appelant qui
 * valide un type de compte a besoin de la liste des types valides ; la
 * dupliquer localement ferait diverger deux definitions de « type de compte
 * bannissable ». Le service les implemente en les referencant.
 */
interface TokenRevocationContract
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_USER = 'user';

    public const TYPE_TRAVEL_CUSTOMER = 'travel_customer';

    public const TYPE_SUPER_ADMIN = 'super_admin';

    /**
     * Bannit un compte, tous guards confondus.
     *
     * @param  string  $type  L'une des constantes TYPE_*.
     * @param  int  $id  Identifiant du compte dans son provider.
     * @param  CarbonImmutable|null  $until  Fin du bannissement (`null` = permanent).
     * @param  string|null  $reason  Motif lisible, journalise.
     * @param  int|null  $actorId  Auteur du bannissement (`null` = systeme).
     */
    public function ban(string $type, int $id, ?CarbonImmutable $until = null, ?string $reason = null, ?int $actorId = null): void;

    /**
     * Leve un bannissement (le compte retrouve l'acces a la requete suivante).
     */
    public function unban(string $type, int $id, ?int $actorId = null): void;

    /**
     * Le compte est-il banni ?
     *
     * Fail-closed : une implementation dont le store est injoignable doit
     * lever, jamais repondre « non banni » par defaut.
     */
    public function isBanned(string $type, int $id): bool;
}
