<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Issue #1813 — une modification de taux légal (barème fiscal ou cotisation
 * sociale) a été soumise pour validation par un RH/comptable.
 */
class TaxRateSubmittedForValidation
{
    use Dispatchable;

    public function __construct(
        public readonly string $tableName,
        public readonly int $recordId,
        public readonly int $actorId,
    ) {
    }
}
