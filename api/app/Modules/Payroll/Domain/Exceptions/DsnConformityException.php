<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use RuntimeException;

/**
 * DSN non conforme (cahier technique NEODeS) — #7936.
 *
 * Levee par `DsnExportService::build()` lorsqu'un controle bloquant echoue :
 * une DSN structurellement incomplete serait rejetee par net-entreprises,
 * mais surtout elle declarerait des montants faux (net verse sans base
 * assujettie, PAS sans taux...) sans que l'employeur en soit averti.
 *
 * Choix produit : echouer bruyamment plutot que produire un fichier
 * silencieusement inexploitable. Les anomalies sont toutes remontees d'un
 * coup (pas seulement la premiere) pour permettre une correction en un passage.
 */
final class DsnConformityException extends RuntimeException
{
    /**
     * @param  list<string>  $anomalies  Messages d'anomalie lisibles.
     */
    public function __construct(private readonly array $anomalies)
    {
        parent::__construct(sprintf(
            'DSN non conforme : %d anomalie(s) bloquante(s).%s%s',
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
