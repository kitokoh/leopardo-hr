<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * #7937 — Facture non conforme a la norme europeenne EN16931.
 *
 * Levee par `FacturXGenerator::build()` lorsqu'un controle bloquant echoue.
 *
 * Choix produit : **echouer bruyamment**. Un XML Factur-X structurellement
 * incomplet est rejete par la plateforme de dematerialisation (PDP) et, en
 * France, une facture electronique non conforme n'a **aucune valeur fiscale**
 * — l'emettre quand meme revient a ne pas facturer. Les anomalies sont toutes
 * remontees d'un coup pour permettre une correction en un passage.
 */
final class FacturXConformityException extends RuntimeException
{
    /**
     * @param  list<string>  $anomalies
     */
    public function __construct(private readonly array $anomalies)
    {
        parent::__construct(sprintf(
            'Facture non conforme EN16931 : %d anomalie(s) bloquante(s).%s%s',
            count($anomalies),
            PHP_EOL,
            implode(PHP_EOL, array_map(static fn (string $a): string => ' - '.$a, $anomalies)),
        ));
    }

    /**
     * @return list<string>
     */
    public function anomalies(): array
    {
        return $this->anomalies;
    }
}
