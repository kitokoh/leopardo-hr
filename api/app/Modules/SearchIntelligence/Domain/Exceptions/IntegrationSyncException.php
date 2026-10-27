<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Exceptions;

use RuntimeException;

/**
 * GSI-12 (#8523) point 1 — échec d'un appel à un fournisseur externe.
 *
 * Cette exception existe pour qu'une panne soit VISIBLE : elle porte le
 * fournisseur, un motif lisible et un drapeau « réessayable ». Le job de
 * synchronisation écrit le motif dans `integrations.last_error` ; l'écran
 * affiche donc une erreur réelle au lieu d'un tableau vide trompeur.
 *
 * Le message ne doit JAMAIS contenir un jeton : les clients HTTP qui créent
 * cette exception sont responsables de la rédaction (`[redacted]`).
 */
final class IntegrationSyncException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $reason,
        public readonly bool $retryable = true,
    ) {
        parent::__construct(sprintf('Sync %s impossible : %s', $provider, $reason));
    }

    /** Un jeton expiré n'est pas réessayable : il faut reconnecter le compte. */
    public static function unauthorized(string $provider): self
    {
        return new self($provider, (string) __('searchintel.unauthorized'), false);
    }

    /** Quota dépassé : la reprise planifiée suffira. */
    public static function quotaExceeded(string $provider): self
    {
        return new self($provider, (string) __('searchintel.quota'), true);
    }

    /** Réponse illisible : le fournisseur a changé son contrat. */
    public static function malformed(string $provider, string $detail): self
    {
        return new self($provider, (string) __('searchintel.malformed', ['detail' => $detail]), false);
    }

    /** Panne réseau / 5xx. */
    public static function transport(string $provider, string $detail): self
    {
        return new self($provider, (string) __('searchintel.transport', ['detail' => $detail]), true);
    }
}
