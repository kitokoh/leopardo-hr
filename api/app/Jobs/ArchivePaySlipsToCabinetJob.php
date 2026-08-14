<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PayrollRun;
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
 * F-09/#1817 — Archivage automatique des bulletins PDF dans le Cabinet employé.
 *
 * Dispatché par `PayrollClosingService::lock()` après verrouillage réussi du
 * run. Pour chaque bulletin validé du run :
 *   1. génère le PDF via `PaySlipPdfGenerator::generate($slip)` ;
 *   2. le stocke sur le disque privé `local`
 *      (`storage/app/private/payslips/{company}/{year}/{month}/slip_{employee}_{run}.pdf`) ;
 *   3. crée un `CabinetDocument` `document_type = payslip`, `read_only = true` ;
 *   4. écrit l'audit `payslip_archived` (un par bulletin).
 *
 * Idempotence : si un document existe déjà pour le même chemin (run + employé
 * + période), le bulletin est ignoré et `payslip_already_archived` est tracé.
 * Un échec sur un bulletin est accumulé et re-lancé en fin de job pour que le
 * retry reprenne uniquement les bulletins manquants.
 */
class ArchivePaySlipsToCabinetJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    private ?string $resolvedCompanyId = null;

    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $actorId,
    ) {
        $this->onQueue('documents');
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

        // Tenant context (search_path + current_company) is already active at
        // this point thanks to EnsureTenantContext.
        // Bulletins « validés » du run : dans le flux API ils passent à
        // `validated` au moment de la validation RH ; via le service seul ils
        // peuvent rester `calculated` — un run verrouillé doit archiver les
        // deux (même convention que WarmPaySlipPdfPathsForPayrollRunJob).
        $slips = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        if ($slips->isEmpty()) {
            Log::info("ArchivePaySlipsToCabinetJob: no validated slips for run #{$run->id}.");

            return;
        }

        $disk = Storage::disk('local');
        $companyKey = $this->legacyCompanyKey($run->company_id);
        $year = $run->period_end?->format('Y') ?? date('Y');
        $month = $run->period_end?->format('m') ?? date('m');

        $failures = [];

        foreach ($slips as $slip) {
            $path = sprintf('payslips/%s/%s/%s/slip_%d_%d.pdf', $run->company_id, $year, $month, $slip->employee_id, $run->id);

            try {
                // Idempotence : un bulletin déjà archivé pour ce run/employé
                // n'est jamais dupliqué (double lock, retry, re-dispatch).
                $existing = CabinetDocument::query()
                    ->where('company_id', $companyKey)
                    ->where('employee_id', $slip->employee_id)
                    ->where('document_type', 'payslip')
                    ->where('path', $path)
                    ->first();

                if ($existing !== null) {
                    Log::info("ArchivePaySlipsToCabinetJob: payslip_already_archived — slip #{$slip->id} run #{$run->id} (document #{$existing->id}).");

                    continue;
                }

                $binary = $generator->generate($slip);
                $disk->put($path, $binary);

                $document = CabinetDocument::create([
                    'company_id' => $companyKey,
                    'employee_id' => $slip->employee_id,
                    'folder_id' => null,
                    'name' => sprintf('Bulletin de paie %s — run #%d', $slip->period_start?->format('Y-m') ?? $year, $run->id),
                    'original_name' => sprintf('bulletin_%d_%s.pdf', $slip->employee_id, $slip->period_start?->format('Y_m') ?? $year),
                    'mime_type' => 'application/pdf',
                    'size' => strlen($binary),
                    'disk' => 'local',
                    'path' => $path,
                    'notes' => sprintf('payroll_run_id=%d', $run->id),
                    'document_type' => 'payslip',
                    'read_only' => true,
                ]);

                AuditLog::create([
                    'company_id' => $run->company_id,
                    'user_id' => $this->actorId,
                    'action' => 'payslip_archived',
                    'auditable_type' => $slip->getMorphClass(),
                    'auditable_id' => $slip->id,
                    'old_values' => null,
                    'new_values' => [
                        'document_id' => $document->id,
                        'payroll_run_id' => $run->id,
                        'path' => $path,
                    ],
                    'metadata' => ['read_only' => true],
                ]);

                Log::info("ArchivePaySlipsToCabinetJob: payslip_archived — slip #{$slip->id} run #{$run->id} document #{$document->id}.");
            } catch (Throwable $e) {
                Log::error("ArchivePaySlipsToCabinetJob: failed for slip #{$slip->id} run #{$run->id}: {$e->getMessage()}");
                $failures[] = $slip->id;
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException('ArchivePaySlipsToCabinetJob: '.count($failures).' slip(s) failed to archive: #'.implode(', #', $failures).'.');
        }
    }

    /**
     * `cabinet_documents.company_id` est une clé historique entière (les
     * tenants modernes portent des UUID) — même convention que
     * `CabinetService::legacyCompanyKey()`.
     */
    private function legacyCompanyKey(string|int|null $companyId): int
    {
        if (is_numeric($companyId)) {
            return (int) $companyId;
        }

        return 0;
    }
}
