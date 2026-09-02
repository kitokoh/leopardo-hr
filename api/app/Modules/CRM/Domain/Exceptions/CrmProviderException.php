<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

/**
 * Erreur fournisseur (429/5xx/timeout) — déclenche le dead-letter après
 * épuisement des tentatives. `retryable` distingue un 429/5xx (retry
 * possible) d'une erreur définitive (400, numéro invalide…) qui doit
 * dead-letter immédiatement.
 */
final class CrmProviderException extends CrmChannelException
{
    public function __construct(
        string $message,
        private readonly bool $retryable = true,
        private readonly string $providerErrorCode = 'PROVIDER_ERROR',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'CRM_PROVIDER_ERROR';
    }

    public function httpStatus(): int
    {
        return 502;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function providerErrorCode(): string
    {
        return $this->providerErrorCode;
    }
}
