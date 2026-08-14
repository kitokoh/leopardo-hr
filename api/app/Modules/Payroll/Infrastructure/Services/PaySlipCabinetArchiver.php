<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Programme FOCUS — F-09/#1548 (issue #1817) : archivage automatique des
 * bulletins PDF dans le Cabinet employé après clôture.
 *
 * À la clôture d'un run (`PayrollClosingService::lock()`), chaque bulletin
 * validé est :
 *   1. généré en PDF via PaySlipPdfGenerator::generate() ;
 *   2. stocké sur le disque privé (`local` → storage/app/private) sous
 *      `payslips/{company}/{year}/{month}/slip_{employee}_{run}.pdf` ;
 *   3. référencé par un `CabinetDocument` en lecture seule
 *      (`read_only = true`, `document_type = 'payslip'`, `pay_slip_id`),
 *      scopé à l'employé (le placard numérique est un espace personnel) ;
 *   4. tracé par un audit log `payslip_archived`.
 *
 * Idempotent : un bulletin déjà archivé (document_type='payslip' pour ce
 * pay_slip_id) est ignoré (`payslip_already_archived`), même si le job est
 * rejoué (retry queue, double dispatch).
 */
class PaySlipCabinetArchiver
{
    public function __construct(
        private readonly PaySlipPdfGenerator $pdfGenerator,
    ) {
    }

    /**
     * Archive tous les bulletins validés du run dans le Cabinet de chaque
     * employé concerné.
     *
     * @return array{archived: int, skipped: int}
     */
    public function archiveRun(PayrollRun $run): array
    {
        $archived = 0;
        $skipped = 0;

        foreach ($run->paySlips()->get() as $slip) {
            if ($this->alreadyArchived($slip)) {
                Log::info("payslip_already_archived: slip #{$slip->id} (run #{$run->id})");

                $skipped++;

                continue;
            }

            $this->archiveSlip($run, $slip);
            $archived++;
        }

        return ['archived' => $archived, 'skipped' => $skipped];
    }

    private function alreadyArchived(PaySlip $slip): bool
    {
        return CabinetDocument::query()
            ->where('pay_slip_id', $slip->id)
            ->where('document_type', 'payslip')
            ->exists();
    }

    private function archiveSlip(PayrollRun $run, PaySlip $slip): void
    {
        $binary = $this->pdfGenerator->generate($slip);

        $year = $run->period_end->format('Y');
        $month = $run->period_end->format('m');
        $path = sprintf(
            'payslips/%s/%s/%s/slip_%s_%s.pdf',
            $run->company_id,
            $year,
            $month,
            $slip->employee_id,
            $run->id
        );

        // Disque `local` = stockage privé (storage/app/private) — le disque
        // canonique des documents sensibles (bulletins, cabinet).
        Storage::disk('local')->put($path, $binary);

        $period = $run->period_start->format('Y-m').' → '.$run->period_end->format('Y-m-d');

        DB::transaction(function () use ($run, $slip, $path, $binary, $period): void {
            $document = CabinetDocument::create([
                'company_id' => $this->legacyCompanyKey($slip->company_id),
                'employee_id' => $slip->employee_id,
                'name' => "Bulletin de paie — {$period}",
                'original_name' => sprintf('bulletin_%s_%s.pdf', $slip->employee_id, $run->id),
                'mime_type' => 'application/pdf',
                'size' => strlen($binary),
                'disk' => 'local',
                'path' => $path,
                'read_only' => true,
                'document_type' => 'payslip',
                'pay_slip_id' => $slip->id,
            ]);

            AuditLog::create([
                'company_id' => $slip->company_id,
                'user_id' => $run->locked_by,
                'action' => 'payslip_archived',
                'auditable_type' => $slip->getMorphClass(),
                'auditable_id' => $slip->id,
                'old_values' => [],
                'new_values' => [
                    'cabinet_document_id' => $document->id,
                    'path' => $path,
                    'read_only' => true,
                ],
                'metadata' => [
                    'payroll_run_id' => $run->id,
                    'period_start' => $run->period_start->toDateString(),
                    'period_end' => $run->period_end->toDateString(),
                ],
            ]);
        });
    }

    /**
     * Les tables Cabinet historiques portent un company_id NUMÉRIQUE alors
     * que les tenants modernes utilisent des UUID — même garde que
     * CabinetService::legacyCompanyKey() : les valeurs numériques sont
     * conservées, sinon 0 (l'isolation réelle du placard passe par
     * employee_id, jamais par company_id).
     */
    private function legacyCompanyKey(mixed $companyId): int
    {
        return is_numeric($companyId) ? (int) $companyId : 0;
    }
}
