<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * #7938 (V2-5) — Facture non conforme au profil UBL-TR 1.2 de la GİB.
 *
 * Levee par `UblTrInvoiceGenerator::build()` des qu'un controle bloquant
 * echoue.
 *
 * Pourquoi echouer bruyamment : la GİB (Gelir İdaresi Başkanlığı) rejette le
 * document a l'import, et une facture rejetee n'ouvre **aucun droit a
 * deduction** pour l'acheteur. Contrairement a un simple avertissement, un
 * fichier non conforme emis quand meme expose l'emetteur a une amende pour
 * defaut de facturation (VUK m.353). Les anomalies sont toutes remontees en un
 * seul passage pour permettre une correction unique.
 */
final class EfaturaConformityException extends RuntimeException
{
    /**
     * @param  list<string>  $anomalies
     */
    public function __construct(private readonly array $anomalies)
    {
        parent::__construct(sprintf(
            'Facture non conforme UBL-TR 1.2 (GIB) : %d anomalie(s) bloquante(s).%s%s',
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
