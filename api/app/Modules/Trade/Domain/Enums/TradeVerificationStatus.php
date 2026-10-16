<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Statut de verification d'une contrepartie — BC-36 TRADE, TRADE-003/#8478.
 *
 * v1 : la verification est une SAISIE MANUELLE TRACEE (qui, quand, note).
 * Aucun service de verification d'entreprise n'etant souscrit, `Verified`
 * signifie « verifie par un membre de l'equipe » — JAMAIS « verifie
 * automatiquement ». L'interface doit le presenter ainsi : une exigence
 * d'honnetete qui evite de laisser croire a un controle externe.
 */
enum TradeVerificationStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
