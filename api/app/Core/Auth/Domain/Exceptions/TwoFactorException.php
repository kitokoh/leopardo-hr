<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5436 — Erreurs métier de la 2FA des comptes entreprise.
 *
 * Codes d'erreur rendus par le handler (message i18n côté client) :
 * - TWO_FACTOR_INVALID (422) : code TOTP/code de récupération invalide ;
 * - TWO_FACTOR_REQUIRED (403) : la politique tenant impose la 2FA (rôle
 *   sensible) et le compte n'est pas enrôlé ;
 * - TWO_FACTOR_ALREADY_ENABLED (409) : enrôlement/activation déjà faits ;
 * - TWO_FACTOR_CHALLENGE_EXPIRED (401) : challenge de connexion expiré/absent.
 */
final class TwoFactorException extends DomainException
{
    public static function invalidCode(): self
    {
        return new self('Invalid two-factor code', 422, 'TWO_FACTOR_INVALID');
    }

    public static function required(): self
    {
        return new self('Two-factor authentication is required for this account', 403, 'TWO_FACTOR_REQUIRED');
    }

    public static function alreadyEnabled(): self
    {
        return new self('Two-factor authentication is already enabled', 409, 'TWO_FACTOR_ALREADY_ENABLED');
    }

    public static function challengeExpired(): self
    {
        return new self('Two-factor challenge expired or missing', 401, 'TWO_FACTOR_CHALLENGE_EXPIRED');
    }
}
