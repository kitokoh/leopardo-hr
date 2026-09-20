<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Statut d'un intent de paiement en ligne marketplace (BC-17 RETAIL,
 * #7812).
 *
 * Machine d'etats :
 *   pending    → processing | succeeded | failed | expired
 *   processing → succeeded | failed | expired
 *   succeeded  → refunded
 *   failed / expired / refunded : terminaux.
 *
 * Les transitions ne sont appliquees QUE par RetailPaymentService (webhook
 * signe verifie ou reconciliation provider) — jamais depuis une entree
 * client non authentifiee.
 */
enum RetailPaymentIntentStatus: string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Succeeded = 'succeeded';

    case Failed = 'failed';

    case Expired = 'expired';

    case Refunded = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Processing, self::Succeeded, self::Failed, self::Expired], true),
            self::Processing => in_array($target, [self::Succeeded, self::Failed, self::Expired], true),
            self::Succeeded => $target === self::Refunded,
            self::Failed, self::Expired, self::Refunded => false,
        };
    }

    /**
     * Statuts non termines, candidats a la reconciliation
     * (`retail:payments:reconcile`).
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Processing->value];
    }
}
