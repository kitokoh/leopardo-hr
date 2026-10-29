<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #9054 (BOS-006V2-8) — levée quand un référentiel pays prétend être en
 * `production` sans fiche de validation experte signée.
 *
 * Règle opposable de la spec `CONFORMITE_LEGALE_MULTI_MARCHES_2026.md` § 4.4 :
 * `confidenceLevel` vaut `pilot` par défaut, et le passage à `production`
 * exige une fiche `docs/payroll/VALIDATION_EXPERTE.md` signée par un
 * expert-comptable / gestionnaire de paie local. Aucune automatisation ne
 * peut produire cette signature : le code ne fait que REFUSER la promesse
 * non validée (posture #7922 — aucune promesse de conformité non prouvée).
 *
 * Le message nomme le pays et la preuve manquante pour que l'exploitant
 * sache exactement quoi produire, sans jamais suggérer un contournement.
 */
class UnvalidatedConfidenceLevelException extends DomainException
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $claimedLevel,
        public readonly string $reason,
    ) {
        parent::__construct(
            sprintf(
                'Le référentiel de paie « %s » revendique le niveau « %s » sans validation experte : %s. '
                .'Renseignez une fiche signée dans docs/payroll/VALIDATION_EXPERTE.md (voir #7941) '
                .'ou repassez le référentiel en « pilot ».',
                $countryCode,
                $claimedLevel,
                $reason
            ),
            422,
            'UNVALIDATED_CONFIDENCE_LEVEL'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'UNVALIDATED_CONFIDENCE_LEVEL';
    }
}
