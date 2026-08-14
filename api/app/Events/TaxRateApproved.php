<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Issue #1813 — une modification de taux légal a été approuvée par le
 * platform admin (double signature) et devient active.
 */
class TaxRateApproved
{
    use Dispatchable;

    public function __construct(
        public readonly string $tableName,
        public readonly int $recordId,
        public readonly int $adminId,
    ) {
    }
}
