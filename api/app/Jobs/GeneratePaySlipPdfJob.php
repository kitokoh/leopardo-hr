<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Plan 62 — Génération asynchrone des bulletins de paie PDF.
 *
 * Dispatched on the `pdf` queue.
 * Generates a PDF for a single employee's pay slip and notifies them via push.
 */
class GeneratePaySlipPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $employeeId,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(PushNotificationService $pushService): void
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);
        if ($run === null) {
            Log::warning("GeneratePaySlipPdfJob: PayrollRun #{$this->payrollRunId} not found.");

            return;
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->withoutGlobalScopes()->find($this->employeeId);
        if ($employee === null) {
            Log::warning("GeneratePaySlipPdfJob: Employee #{$this->employeeId} not found.");

            return;
        }

        /** @var Company|null $company */
        $company = Company::query()->find($run->company_id);
        if ($company === null) {
            return;
        }

        /** @var PaySlip|null $slip */
        $slip = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($slip === null) {
            Log::warning("GeneratePaySlipPdfJob: No PaySlip for employee #{$this->employeeId} in run #{$this->payrollRunId}.");

            return;
        }

        app()->instance('current_company', $company);

        try {
            // Build PDF HTML via Blade template
            $html = view('pdf.payslip', [
                'slip' => $slip,
                'employee' => $employee,
                'company' => $company,
                'run' => $run,
            ])->render();

            // Generate PDF using dompdf (barryvdh/laravel-dompdf)
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $binary = $pdf->output();

            // Store: storage/app/payslips/{tenant}/{year}/{month}/{employee_id}.pdf
            $year = $run->period_end->format('Y');
            $month = $run->period_end->format('m');
            $path = "payslips/{$company->id}/{$year}/{$month}/{$employee->id}.pdf";

            Storage::disk('local')->put($path, $binary);

            $slip->update(['pdf_path' => $path]);

            Log::info("GeneratePaySlipPdfJob: PDF generated at {$path}");

            // Notify employee via push notification
            $this->notifyEmployee($pushService, $employee, $run);
        } finally {
            app()->forgetInstance('current_company');
        }
    }

    private function notifyEmployee(PushNotificationService $pushService, Employee $employee, PayrollRun $run): void
    {
        try {
            $period = $run->period_end->format('M Y');
            $pushService->sendToEmployee($employee, [
                'title' => 'Bulletin de paie disponible',
                'body' => "Votre bulletin de paie {$period} est prêt.",
                'data' => [
                    'type' => 'pay_slip_ready',
                    'payroll_run_id' => $run->id,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning("GeneratePaySlipPdfJob: Push notification failed for employee #{$employee->id}: {$e->getMessage()}");
        }
    }
}
