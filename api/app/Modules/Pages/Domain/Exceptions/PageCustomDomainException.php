<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Refus du flux de domaines personnalisés (#8726).
 *
 * Chaque refus porte un code stable, utilisable côté API et dans les tests sans
 * comparer des phrases. Les messages sont en français : ils sont affichés au
 * tenant dans l'écran de configuration du domaine.
 */
final class PageCustomDomainException extends DomainException
{
    public static function invalidHost(string $reason): self
    {
        return new self(
            __('pages.domain_invalid', ['reason' => $reason]),
            422,
            'CUSTOM_DOMAIN_INVALID',
        );
    }

    public static function hostTaken(): self
    {
        // Volontairement vague : ne pas révéler à quel tenant appartient le host.
        return new self(
            __('pages.domain_taken'),
            409,
            'CUSTOM_DOMAIN_TAKEN',
        );
    }

    public static function alreadyLinked(): self
    {
        return new self(
            __('pages.domain_already_linked'),
            409,
            'CUSTOM_DOMAIN_ALREADY_LINKED',
        );
    }

    public static function entitlementMissing(): self
    {
        return new self(
            __('pages.domain_entitlement_missing'),
            402,
            'CUSTOM_DOMAIN_ENTITLEMENT_MISSING',
        );
    }

    public static function quotaExceeded(int $limit): self
    {
        return new self(
            __('pages.domain_quota_exceeded', ['limit' => $limit]),
            402,
            'CUSTOM_DOMAIN_QUOTA_EXCEEDED',
        );
    }

    public static function removed(): self
    {
        return new self(
            __('pages.domain_removed'),
            410,
            'CUSTOM_DOMAIN_REMOVED',
        );
    }

    public static function planExpired(): self
    {
        return new self(
            __('pages.domain_plan_expired'),
            402,
            'CUSTOM_DOMAIN_PLAN_EXPIRED',
        );
    }
}
