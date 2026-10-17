<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Verdict d'un rapprochement (BC-38, #8692).
 */
enum ReconciliationStatus: string
{
    case Ok = 'ok';
    case Discrepancy = 'discrepancy';

    public static function fromDifference(int $difference): self
    {
        return $difference === 0 ? self::Ok : self::Discrepancy;
    }
}
