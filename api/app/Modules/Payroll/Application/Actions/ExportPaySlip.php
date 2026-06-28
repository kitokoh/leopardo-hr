<?php

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;

class ExportPaySlip
{
    public function __construct(
        private readonly PaySlipPdfGenerator $pdfGenerator,
    ) {}

    public function handle(PaySlip $paySlip): string
    {
        return $this->pdfGenerator->generate($paySlip);
    }
}
