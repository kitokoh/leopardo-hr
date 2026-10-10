<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Exception métier de la verticale FUNDRAISING — codes d'erreur stables et
 * documentés (spec SOLUTION_FUNDRAISING.md §5.2).
 *
 * Le renderer dédié de `bootstrap/app.php` expose `{error, message,
 * localized_message}` : c'est le SEUL moyen de servir un code stable au
 * client API (leçon #8247 — un `abort(404, 'CODE')` n'expose jamais CODE).
 */
final class FundraisingException extends DomainException
{
    public static function fundraiserNotFound(): self
    {
        return new self('Cagnotte introuvable.', 404, 'FUNDRAISER_NOT_FOUND');
    }

    public static function fundraiserNotActive(): self
    {
        return new self('Cette cagnotte n\'accepte pas de contributions actuellement.', 422, 'FUNDRAISER_NOT_ACTIVE');
    }

    public static function contributionNotFound(): self
    {
        return new self('Contribution introuvable.', 404, 'CONTRIBUTION_NOT_FOUND');
    }

    public static function invalidContributionAmount(string $reason): self
    {
        return new self('Montant de contribution invalide : '.$reason, 422, 'INVALID_CONTRIBUTION_AMOUNT');
    }

    public static function gatewayNotConfigured(string $gateway): self
    {
        return new self('La passerelle de paiement « '.$gateway.' » n\'est pas configurée.', 503, 'PAYMENT_GATEWAY_NOT_CONFIGURED');
    }

    public static function payoutExceedsBalance(): self
    {
        return new self('Le montant du reversement dépasse le solde disponible de la cagnotte.', 422, 'PAYOUT_AMOUNT_EXCEEDS_BALANCE');
    }

    public static function payoutNotFound(): self
    {
        return new self('Reversement introuvable.', 404, 'PAYOUT_NOT_FOUND');
    }

    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self('Transition de statut invalide : '.$from.' → '.$to.'.', 422, 'INVALID_STATUS_TRANSITION');
    }

    public static function webhookSignatureInvalid(): self
    {
        return new self('Signature du webhook invalide.', 401, 'WEBHOOK_SIGNATURE_INVALID');
    }
}
