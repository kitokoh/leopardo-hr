<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use RuntimeException;

/**
 * BC-10 RECRUITMENT — le fournisseur n'a pas pu être interrogé.
 *
 * Distingue deux familles, parce qu'elles appellent des réactions opposées :
 *
 * - **rétentive** (`$retryable = true`) : panne réseau, 5xx, quota — le job
 *   doit réessayer avec un backoff, le connecteur reste `connected` ;
 * - **définitive** (`$retryable = false`) : identifiants refusés, fournisseur
 *   non câblé — réessayer ne servira à rien, le connecteur passe en `error` et
 *   l'écran super admin doit inviter à reconfigurer.
 */
final class JobBoardUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public static function notWired(string $provider): self
    {
        return new self(
            "Le connecteur « {$provider} » n'est pas encore câblé : aucune API n'est appelée. "
            .'Utilisez un flux XML personnalisé ou attendez la mise en service du fournisseur.',
            retryable: false,
        );
    }

    public static function transport(string $provider, string $reason): self
    {
        return new self("Fournisseur « {$provider} » injoignable : {$reason}", retryable: true);
    }

    public static function rejected(string $provider, string $reason): self
    {
        return new self("Fournisseur « {$provider} » a refusé la requête : {$reason}", retryable: false);
    }
}
