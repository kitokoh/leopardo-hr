<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\GenerateBankExportJob;
use App\Jobs\ProcessPayrollBatchJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

/**
 * #8057 — deux workers ne doivent jamais traiter le même run de paie ou le
 * même export bancaire en parallèle : les jobs de calcul/export portent un
 * verrou d'unicité au niveau queue (`ShouldBeUnique`), clé dérivée du run.
 */
class PayrollJobUniquenessTest extends TestCase
{
    public function test_process_payroll_batch_job_is_unique_per_company_and_run(): void
    {
        $job = new ProcessPayrollBatchJob(42, 'company-uuid');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('payroll-run:company-uuid:42', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
    }

    public function test_generate_bank_export_job_is_unique_per_export(): void
    {
        $job = new GenerateBankExportJob(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('bank-export:7', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
    }
}
