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
 *
 * Le message passe par le catalogue (`payroll.unvalidated_confidence_level`)
 * et non par un littéral accentué : la garde i18n #5432 refuse tout nouveau
 * message français codé en dur sur une surface d'exception de domaine. Les
 * paramètres sont liés via `:country`, `:level` et `:reason`.
 */
class UnvalidatedConfidenceLevelException extends DomainException
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $claimedLevel,
        public readonly string $reason,
    ) {
        parent::__construct(
            (string) __('payroll.unvalidated_confidence_level', [
                'country' => $countryCode,
                'level' => $claimedLevel,
                'reason' => $reason,
            ]),
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
