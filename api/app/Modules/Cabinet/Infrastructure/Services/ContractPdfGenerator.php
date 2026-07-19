<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Infrastructure\Services;

use App\Modules\HR\Domain\Models\Contract;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;

class ContractPdfGenerator
{
    public function generate(Contract $contract): string
    {
        $contract->load(['employee', 'company']);

        // Runs outside the HTTP request lifecycle in some call paths (jobs),
        // so the locale must be applied explicitly before rendering.
        App::setLocale(I18nCatalog::normalizeLocale(
            $contract->employee?->preferred_language ?? $contract->company?->language
        ));

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'employee' => $contract->employee,
            'company' => $contract->company,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }
}

