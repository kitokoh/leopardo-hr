<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Nature d'un journal comptable — issue #9370.
 *
 * Le type porte deux informations que le code déduisait auparavant du préfixe
 * des comptes : le **journal FEC** d'une écriture (`VE`, `AC`, `BQ`, `OD`…) et
 * le **compte de trésorerie** par défaut d'un journal de trésorerie.
 */
enum JournalType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Bank = 'bank';
    case Cash = 'cash';
    case Payroll = 'payroll';
    case Misc = 'misc';
    case Opening = 'opening';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Le journal est-il un journal de trésorerie (banque ou caisse) ?
     * Seuls ceux-là portent un `default_account_code` mouvementé par les
     * encaissements et décaissements.
     */
    public function isTreasury(): bool
    {
        return in_array($this, [self::Bank, self::Cash], true);
    }

    /**
     * Libellé par défaut du journal, localisé.
     */
    public function defaultLabel(): string
    {
        return (string) __('accounting.journal_types.'.$this->value);
    }
}
