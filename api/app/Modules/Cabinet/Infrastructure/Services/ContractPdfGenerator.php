<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Infrastructure\Services;

use App\Modules\HR\Domain\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractPdfGenerator
{
    public function generate(Contract $contract): string
    {
        $contract->load(['employee', 'company']);

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'employee' => $contract->employee,
            'company' => $contract->company,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }
}

