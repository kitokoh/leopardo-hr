<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #5832 (EDU-016) — refus métier du flux de facturation scolaire v2.
 *
 * Le flux `fee-charges` + `fee-charges/{id}/payments` + `.../waive` est
 * idempotent et terminal : ces trois refus sont contractuels et portés par un
 * code stable consommé par les clients web/mobile et par les tests de recette.
 *
 * Le code est porté par `errorCode()` (rendu JSON `error`) — passer par
 * `abort()` ne suffit pas : le renderer générique remplace le message par un
 * code mappé par statut (`VALIDATION_FAILED`), ce qui casserait le contrat.
 */
final class EduFeeException extends DomainException
{
    private function __construct(string $message, int $statusCode, string $errorCode)
    {
        parent::__construct($message, $statusCode, $errorCode);
    }

    /**
     * Charge déjà soldée, abandonnée ou annulée : plus aucun encaissement.
     */
    public static function terminal(): self
    {
        return new self('EDU_FEE_TERMINAL', 422, 'EDU_FEE_TERMINAL');
    }

    /**
     * L'encaissement dépasserait le montant dû (le trop-perçu n'est jamais
     * absorbé silencieusement — il doit être remboursé ou reventilé).
     */
    public static function overpayment(): self
    {
        return new self('EDU_FEE_OVERPAYMENT', 422, 'EDU_FEE_OVERPAYMENT');
    }

    /**
     * Devise de l'encaissement différente de celle de la charge.
     */
    public static function currencyMismatch(): self
    {
        return new self('EDU_FEE_CURRENCY_MISMATCH', 422, 'EDU_FEE_CURRENCY_MISMATCH');
    }
}
