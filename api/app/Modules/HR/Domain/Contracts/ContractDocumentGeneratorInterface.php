<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use App\Modules\HR\Domain\Models\Contract;

/**
 * Contract for generating a printable document (PDF) for an employment
 * contract. Owned by HR so ContractController depends on this interface
 * instead of importing App\Modules\HR\Infrastructure\Services\ContractPdfGenerator
 * directly (PA2-ARCH-003). Bound to the PDF implementation by
 * HRServiceProvider (composition root décentralisée).
 */
interface ContractDocumentGeneratorInterface
{
    /**
     * @return string raw PDF binary content
     */
    public function generate(Contract $contract): string;
}
