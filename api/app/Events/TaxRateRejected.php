<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Issue #1813 — une modification de taux légal a été rejetée par le platform
 * admin (motif obligatoire) et repasse en brouillon.
 */
class TaxRateRejected
{
    use Dispatchable;

    public function __construct(
        public readonly string $tableName,
        public readonly int $recordId,
        public readonly int $adminId,
        public readonly string $reason,
    ) {
    }
}
