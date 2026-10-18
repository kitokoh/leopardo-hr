<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Reference metier Trade — BC-36 TRADE.
 *
 * Format : `PREFIX-YYYY-NNNNNN` (`TRD` dossier, `RFQ` demande de prix, `QTE`
 * offre, `ORD` commande, `TTR` cotation transport, `LCE` cout rendu).
 *
 * La reference est UNIQUE PAR TENANT (index unique `company_id` + reference),
 * jamais globale : deux entreprises peuvent parfaitement utiliser la meme
 * reference sans collision — c'est le tenant qui delimite l'espace de
 * nommage, comme pour `VTC-YYYY-NNNNNN`.
 *
 * Ce value object ne genere pas de sequence (cela depend de la base) : il
 * VALIDE et NORMALISE. La generation est assuree par
 * {@see \App\Modules\Trade\Infrastructure\Services\TradeReferenceGenerator}.
 */
final class TradeReference
{
    /** @var array<string, string> Prefixe attendu par type d'objet. */
    private const PREFIXES = [
        'deal' => 'TRD',
        'rfq' => 'RFQ',
        'quote' => 'QTE',
        'order' => 'ORD',
        'transport_request' => 'TTR',
        'cost_estimate' => 'LCE',
    ];

    private function __construct(
        public readonly string $value,
    ) {}

    /**
     * @param  string  $type  Cle de type (`deal`, `rfq`, `quote`, `order`, `transport_request`, `cost_estimate`)
     */
    public static function fromString(string $value, string $type): self
    {
        $normalized = strtoupper(trim($value));

        if (! isset(self::PREFIXES[$type])) {
            throw new InvalidArgumentException(sprintf('Type de reference Trade inconnu : %s.', $type));
        }

        $prefix = self::PREFIXES[$type];

        // PREFIX-AAAA-NNNNNN — sequence au moins 4 chiffres (tolerant aux
        // compteurs > 999 999 pour ne pas bloquer une entreprise a fort volume).
        if (! preg_match('/^'.preg_quote($prefix, '/').'-\d{4}-\d{4,}$/', $normalized)) {
            throw new InvalidArgumentException(sprintf(
                'Reference Trade invalide pour %s : attendu %s-AAAA-NNNNNN, recu « %s ».',
                $type,
                $prefix,
                $value,
            ));
        }

        return new self($normalized);
    }

    /**
     * @return array<string, string>
     */
    public static function prefixes(): array
    {
        return self::PREFIXES;
    }

    public function prefix(): string
    {
        return explode('-', $this->value)[0];
    }

    public function year(): int
    {
        return (int) explode('-', $this->value)[1];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
