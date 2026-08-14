<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
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
 * employé après clôture (F-09/#1548).
 *
 * Dispatched par PayrollClosingService::lock() après verrouillage réussi du
 * run. Pour chaque bulletin du run :
 *   1. génère le PDF via PaySlipPdfGenerator::generate($slip) ;
 *   2. le stocke sur le disque privé `local`
 *      (storage/app/private/payslips/{company}/{year}/{month}/slip_{employee}_{run}.pdf) ;
 *   3. crée un CabinetDocument `document_type = payslip`, `read_only = true` ;
 *   4. trace l'audit `payslip_archived`.
 *
 * Idempotence : si un document existe déjà pour ce run/employé (même chemin),
 * le bulletin est ignoré et un log `payslip_already_archived` est émis.
 * Un échec sur un bulletin n'empêche pas l'archivage des autres ; les erreurs
 * sont loggées et le job est relancé par le worker (retry) pour rattraper les
 * bulletins manquants.
 */
class ArchivePaySlipsToCabinetJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $payrollRunId)
    {
        $this->onQueue('pdf');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);

        return $this->resolvedCompanyId = $run?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(PaySlipPdfGenerator $generator): void
    {
        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->payrollRunId);
        if ($run === null) {
            Log::warning("ArchivePaySlipsToCabinetJob: PayrollRun #{$this->payrollRunId} not found.");

            return;
        }

        // Tenant context (search_path + current_company) déjà actif via
        // EnsureTenantContext. Le run étant verrouillé, tous ses bulletins
        // sont définitifs — on archive les statuts calculés/validés/envoyés.
        $slips = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated', 'sent'])
            ->get();

        foreach ($slips as $slip) {
            $this->archiveSlip($run, $slip, $generator);
        }
    }

    private function archiveSlip(PayrollRun $run, PaySlip $slip, PaySlipPdfGenerator $generator): void
    {
        $year = $run->period_start->format('Y');
        $month = $run->period_start->format('m');
        $path = sprintf(
            'payslips/%s/%s/%s/slip_%d_%d.pdf',
            $run->company_id,
            $year,
            $month,
            $slip->employee_id,
            $run->id
        );

        $existing = CabinetDocument::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $slip->employee_id)
            ->where('document_type', CabinetDocument::TYPE_PAYSLIP)
            ->where('path', $path)
            ->exists();

        if ($existing) {
            Log::info('payslip_already_archived', [
                'payroll_run_id' => $run->id,
                'pay_slip_id' => $slip->id,
                'employee_id' => $slip->employee_id,
                'path' => $path,
            ]);

            return;
        }

        try {
            $binary = $generator->generate($slip);
        } catch (Throwable $e) {
            Log::error("ArchivePaySlipsToCabinetJob: PDF generation failed for pay slip #{$slip->id}: {$e->getMessage()}");

            throw $e;
        }

        $disk = Storage::disk('local');
        $disk->put($path, $binary);

        $document = CabinetDocument::create([
            'company_id' => $run->company_id,
            'employee_id' => $slip->employee_id,
            'folder_id' => null,
            'name' => sprintf('Bulletin de paie %s/%s', $month, $year),
            'original_name' => sprintf('bulletin_%d_%d.pdf', $slip->employee_id, $run->id),
            'mime_type' => 'application/pdf',
            'size' => strlen($binary),
            'disk' => 'local',
            'path' => $path,
            'document_type' => CabinetDocument::TYPE_PAYSLIP,
            'read_only' => true,
        ]);

        AuditLog::create([
            'company_id' => $run->company_id,
            'user_id' => null, // archivage système — aucun acteur humain
            'action' => 'payslip_archived',
            'auditable_type' => $slip->getMorphClass(),
            'auditable_id' => $slip->id,
            'old_values' => [],
            'new_values' => [
                'cabinet_document_id' => $document->id,
                'path' => $path,
                'payroll_run_id' => $run->id,
                'employee_id' => $slip->employee_id,
                'read_only' => true,
            ],
        ]);

        Log::info('payslip_archived', [
            'payroll_run_id' => $run->id,
            'pay_slip_id' => $slip->id,
            'cabinet_document_id' => $document->id,
            'path' => $path,
        ]);
    }
}
