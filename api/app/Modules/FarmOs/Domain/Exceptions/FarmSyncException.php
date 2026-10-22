<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T5.2 Synchronisation refusee.
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmSyncException extends RuntimeException
{
    /**
     * Une operation hors ligne non prevue est refusee : Farm OS ne promet pas
     * une fonction hors ligne qu'il n'implemente pas.
     */
    public static function unsupportedOperation(string $type): self
    {
        return new self('FARM_SYNC_UNSUPPORTED_OPERATION:'.$type);
    }

    public static function missingClientOperationId(): self
    {
        return new self('FARM_SYNC_MISSING_CLIENT_OPERATION_ID');
    }

    /**
     * Un meme identifiant d'operation a ete pousse avec un contenu DIFFERENT :
     * ce n'est pas un rejeu, c'est un conflit d'identifiant.
     */
    public static function payloadMismatch(string $clientOperationId): self
    {
        return new self('FARM_SYNC_PAYLOAD_MISMATCH:'.$clientOperationId);
    }

    public static function operationNotInCompany(int $operationId): self
    {
        return new self('FARM_SYNC_OPERATION_NOT_IN_COMPANY:'.$operationId);
    }

    public static function conflictAlreadyResolved(int $conflictId): self
    {
        return new self('FARM_SYNC_CONFLICT_ALREADY_RESOLVED:'.$conflictId);
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
