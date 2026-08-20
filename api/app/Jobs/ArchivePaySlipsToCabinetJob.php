<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Issue #1817 — Archivage automatique des bulletins PDF dans le Cabinet
 * employé après clôture comptable (F-09/#1548).
 *
 * Dispatché par `PayrollClosingService::lock()` après verrouillage réussi.
 * Pour chaque bulletin du run :
 *   1. génère le PDF via `PaySlipPdfGenerator` (réutilise le rendu F-09) ;
 *   2. stocke sur le disque `private` : payslips/{company}/{year}/{month}/… ;
 *   3. crée un `CabinetDocument` (document_type = 'payslip', read_only = true)
 *      relié au bulletin via `notes.pay_slip_id` ;
 *   4. audit `payslip_archived`.
 *
 * Idempotent : un document déjà archivé pour ce bulletin → skip
 * (`payslip_already_archived`).
 */
class ArchivePaySlipsToCabinetJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $payrollRunId)
    {
        $this->onQueue('pdf');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function tenantCompanyId(): ?string
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        return $run?->company_id;
    }

    public function handle(PaySlipPdfGenerator $pdfGenerator): void
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);
        if ($run === null) {
            Log::warning("ArchivePaySlipsToCabinetJob: PayrollRun #{$this->payrollRunId} not found.");

            return;
        }

        /** @var Company|null $company */
        $company = Company::query()->find($run->company_id);
        if ($company === null) {
            Log::warning("ArchivePaySlipsToCabinetJob: Company #{$run->company_id} not found.");

            return;
        }

        // #5150 — le filtre ne doit pas être limité à `calculated` : via le
        // workflow API (validateRun), les bulletins passent en `validated`
        // avant le verrouillage (`lock`), et `sendSlips` les passe en `sent`.
        // Un filtre `calculated` seul laissait l'archivage vide pour toute
        // clôture passée par l'API (F-09 : archivage Cabinet après clôture).
        $slips = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated', 'sent'])
            ->get();

        foreach ($slips as $slip) {
            try {
                $this->archiveSlip($slip, $pdfGenerator, $company);
            } catch (Throwable $e) {
                Log::warning('payslip_archive_failed', [
                    'run_id' => $run->id,
                    'slip_id' => $slip->id,
                    'employee_id' => $slip->employee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function archiveSlip(PaySlip $slip, PaySlipPdfGenerator $pdfGenerator, Company $company): void
    {
        // Idempotence : déjà archivé pour ce bulletin ?
        $existing = CabinetDocument::query()
            ->where('employee_id', $slip->employee_id)
            ->where('document_type', 'payslip')
            ->where('source_id', $slip->id)
            ->first();

        if ($existing !== null) {
            Log::info('payslip_already_archived', [
                'run_id' => $slip->payroll_run_id,
                'slip_id' => $slip->id,
                'document_id' => $existing->id,
            ]);

            return;
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->withoutGlobalScopes()->find($slip->employee_id);
        if ($employee === null) {
            Log::warning('payslip_archive_no_employee', ['slip_id' => $slip->id]);

            return;
        }

        // 1. Génération du PDF (rendu conforme F-09).
        $pdfBinary = $pdfGenerator->generate($slip);

        // 2. Stockage privé : payslips/{company}/{year}/{month}/slip_{employee}_{run}.pdf
        $periodStart = $slip->period_start;
        $relativePath = sprintf(
            'payslips/%s/%d/%02d/slip_%d_%d.pdf',
            $slip->company_id,
            (int) $periodStart->year,
            (int) $periodStart->month,
            $slip->employee_id,
            $slip->payroll_run_id,
        );

        Storage::disk('private')->put($relativePath, $pdfBinary);

        // 3. Création du document Cabinet (read_only, type payslip).
        $document = CabinetDocument::create([
            'company_id' => $this->legacyCompanyKey($company),
            'employee_id' => $slip->employee_id,
            'folder_id' => null,
            'name' => sprintf('Bulletin de paie %s-%02d', $periodStart->year, $periodStart->month),
            'original_name' => basename($relativePath),
            'mime_type' => 'application/pdf',
            'size' => strlen($pdfBinary),
            'disk' => 'private',
            'path' => $relativePath,
            'notes' => json_encode(['pay_slip_id' => $slip->id], JSON_THROW_ON_ERROR),
            'read_only' => true,
            'document_type' => 'payslip',
            'source_id' => $slip->id,
        ]);

        // 4. Audit immuable.
        AuditLog::create([
            'company_id' => $slip->company_id,
            'user_id' => $slip->employee_id,
            'action' => 'payslip_archived',
            'auditable_type' => $slip->getMorphClass(),
            'auditable_id' => $slip->id,
            'old_values' => null,
            'new_values' => [
                'document_id' => $document->id,
                'path' => $relativePath,
                'run_id' => $slip->payroll_run_id,
            ],
        ]);

        Log::info('payslip_archived', [
            'run_id' => $slip->payroll_run_id,
            'slip_id' => $slip->id,
            'document_id' => $document->id,
        ]);
    }

    /**
     * Vague QA 2026-08-14 — company_id du Cabinet est désormais l'UUID
     * réel de l'entreprise (migration 000019, plus de clé legacy 0).
     */
    private function legacyCompanyKey(Company $company): ?string
    {
        $key = $company->getKey();

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * #4205 : épuisement des retries — log d'alerte (archivage cabinet).
     */
    public function failed(Throwable $e): void
    {
        Log::error('ArchivePaySlipsToCabinetJob.failed', [
            'payroll_run_id' => $this->payrollRunId,
            'exception' => $e->getMessage(),
        ]);
    }

}
