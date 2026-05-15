<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Company;
use App\Models\PaySlip;
use App\Models\PayrollRun;
use App\Services\PaySlipPdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class WarmPaySlipPdfPathsForPayrollRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $payrollRunId) {}

    public function handle(PaySlipPdfGenerator $generator): void
    {
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        if ($run === null || $run->status !== 'validated') {
            return;
        }

        $company = Company::query()->find($run->company_id);
        if ($company === null) {
            return;
        }

        app()->instance('current_company', $company);

        try {
            $disk = Storage::disk('local');

            $slips = PaySlip::query()
                ->where('payroll_run_id', $run->id)
                ->whereIn('status', ['calculated', 'validated'])
                ->get();

            foreach ($slips as $slip) {
                $relativePath = sprintf('pay-slips/%d/%d.pdf', $run->company_id, $slip->id);

                $binary = $generator->generate($slip);
                $disk->put($relativePath, $binary);
                $slip->update(['pdf_path' => $relativePath]);
            }
        } finally {
            app()->forgetInstance('current_company');
        }
    }
}
