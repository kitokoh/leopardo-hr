<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\GenerateDeliveryExportJob;
use App\Jobs\GenerateFuelReportExportJob;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Jobs\GenerateDocumentPdf;
use App\Modules\CRM\Infrastructure\Jobs\ExportCrmDataJob;
use App\Modules\CRM\Infrastructure\Jobs\RetryCrmMessageJob;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

/**
 * #8206 (BOS-017) — les jobs d'export de la liste fermée portent un verrou
 * d'unicité au niveau queue (`ShouldBeUnique`, clé métier dérivée de
 * l'export) : un double-clic / double dispatch ne peut pas produire deux
 * exécutions concurrentes. Pattern aligné sur PayrollJobUniquenessTest
 * (#8057).
 */
class ExportJobUniquenessTest extends TestCase
{
    public function test_delivery_export_job_is_unique_per_export(): void
    {
        $job = new GenerateDeliveryExportJob(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('delivery-export:7', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
    }

    public function test_fuel_report_export_job_is_unique_per_export(): void
    {
        $job = new GenerateFuelReportExportJob(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('fuel-report-export:7', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
    }

    public function test_travel_report_export_job_is_unique_per_export(): void
    {
        $job = new ExportTravelReportJob(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('travel-export:7', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
    }

    public function test_crm_message_retry_job_is_unique_per_message(): void
    {
        $job = new RetryCrmMessageJob('msg-42');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('crm-message-retry:msg-42', $job->uniqueId());
        $this->assertGreaterThan(0, $job->uniqueFor);
    }

    public function test_crm_data_export_job_is_unique_per_export(): void
    {
        $job = new ExportCrmDataJob('exp-42');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('crm-export:exp-42', $job->uniqueId());
        $this->assertGreaterThan(0, $job->uniqueFor);
    }

    public function test_generate_document_pdf_is_unique_per_document_with_explicit_retry(): void
    {
        $document = new AccountingDocument;
        $document->id = 42;

        $job = new GenerateDocumentPdf($document);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('accounting-document-pdf:42', $job->uniqueId());
        $this->assertSame($job->timeout, $job->uniqueFor);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff);
    }
}
