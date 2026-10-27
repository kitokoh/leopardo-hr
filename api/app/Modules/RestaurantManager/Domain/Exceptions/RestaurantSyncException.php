<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Exceptions;

use RuntimeException;

/**
 * Restaurant OS — REST-OS-7 Synchronisation refusee (#8609).
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class RestaurantSyncException extends RuntimeException
{
    /**
     * Une operation hors ligne non prevue est refusee : le module ne promet pas
     * une fonction hors ligne qu'il n'implemente pas.
     */
    public static function unsupportedOperation(string $type): self
    {
        return new self('RESTAURANT_SYNC_UNSUPPORTED_OPERATION:'.$type);
    }

    public static function missingClientOperationId(): self
    {
        return new self('RESTAURANT_SYNC_MISSING_CLIENT_OPERATION_ID');
    }

    /**
     * Un meme identifiant d'operation a ete pousse avec un contenu DIFFERENT :
     * ce n'est pas un rejeu, c'est un conflit d'identifiant.
     */
    public static function payloadMismatch(string $clientOperationId): self
    {
        return new self('RESTAURANT_SYNC_PAYLOAD_MISMATCH:'.$clientOperationId);
    }

    /**
     * Une operation visant une entite n'a pas fourni l'entite ciblee : sans
     * elle, la detection de conflit inter-terminaux est impossible, donc
     * l'operation est refusee plutot qu'appliquee a l'aveugle.
     */
    public static function missingTargetEntity(string $operationType): self
    {
        return new self('RESTAURANT_SYNC_MISSING_TARGET_ENTITY:'.$operationType);
    }

    public static function operationNotInCompany(int $operationId): self
    {
        return new self('RESTAURANT_SYNC_OPERATION_NOT_IN_COMPANY:'.$operationId);
    }

    public static function conflictAlreadyResolved(int $conflictId): self
    {
        return new self('RESTAURANT_SYNC_CONFLICT_ALREADY_RESOLVED:'.$conflictId);
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
