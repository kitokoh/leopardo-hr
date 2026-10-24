<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * BC-38 PAIEMENTS (#8743) — devise absente de l'allowlist du BC.
 *
 * Le repli silencieux sur `DZD` masquait une configuration manquante : un
 * tenant sans devise operait dans une devise que le fournisseur ne traite pas,
 * et le solde retourne etait faux sans que rien ne le signale. Une devise non
 * supportee est desormais une erreur metier explicite, jamais une supposition.
 */
final class UnsupportedCurrencyException extends RuntimeException
{
    public function __construct(
        public readonly string $currency,
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('Devise « %s » non supportee : %s.', $currency, $reason));
    }

    public static function notInAllowlist(string $currency): self
    {
        return new self($currency, 'absente de l\'allowlist des devises supportees');
    }

    public static function notConfigured(): self
    {
        return new self('', 'aucune devise fournie et aucune devise configuree pour l\'entreprise ou le pays');
    }

    public static function malformed(string $currency): self
    {
        return new self($currency, 'format attendu : code ISO 4217 a 3 lettres');
    }
}
