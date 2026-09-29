<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Jobs\GenerateDeliveryExportJob;
use App\Jobs\GenerateFuelReportExportJob;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Jobs\GenerateDocumentPdf;
use App\Modules\CRM\Infrastructure\Jobs\ExportCrmDataJob;
use App\Modules\CRM\Infrastructure\Jobs\RetryCrmMessageJob;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * #8206 (BOS-017, critère 1) — un double dispatch d'un export ne produit
 * qu'une seule exécution : le second dispatch est absorbé par le verrou
 * `ShouldBeUnique` (acquis à la soumission, `PendingDispatch`). Deux exports
 * DISTINCTS passent tous les deux.
 */
class ExportJobDoubleDispatchTest extends TestCase
{
    public function test_double_dispatch_of_delivery_export_pushes_once(): void
    {
        Queue::fake();

        GenerateDeliveryExportJob::dispatch(7);
        GenerateDeliveryExportJob::dispatch(7);

        Queue::assertPushedTimes(GenerateDeliveryExportJob::class, 1);
    }

    public function test_two_distinct_delivery_exports_both_dispatch(): void
    {
        Queue::fake();

        GenerateDeliveryExportJob::dispatch(7);
        GenerateDeliveryExportJob::dispatch(8);

        Queue::assertPushedTimes(GenerateDeliveryExportJob::class, 2);
    }

    public function test_double_dispatch_of_fuel_report_export_pushes_once(): void
    {
        Queue::fake();

        GenerateFuelReportExportJob::dispatch(7);
        GenerateFuelReportExportJob::dispatch(7);

        Queue::assertPushedTimes(GenerateFuelReportExportJob::class, 1);
    }

    public function test_double_dispatch_of_travel_report_export_pushes_once(): void
    {
        Queue::fake();

        ExportTravelReportJob::dispatch(7);
        ExportTravelReportJob::dispatch(7);

        Queue::assertPushedTimes(ExportTravelReportJob::class, 1);
    }

    public function test_double_dispatch_of_crm_message_retry_pushes_once(): void
    {
        Queue::fake();

        RetryCrmMessageJob::dispatch('msg-42');
        RetryCrmMessageJob::dispatch('msg-42');

        Queue::assertPushedTimes(RetryCrmMessageJob::class, 1);
    }

    public function test_double_dispatch_of_crm_data_export_pushes_once(): void
    {
        Queue::fake();

        ExportCrmDataJob::dispatch('exp-42');
        ExportCrmDataJob::dispatch('exp-42');

        Queue::assertPushedTimes(ExportCrmDataJob::class, 1);
    }

    public function test_double_dispatch_of_document_pdf_archiving_pushes_once(): void
    {
        Queue::fake();

        $document = new AccountingDocument;
        $document->id = 42;

        GenerateDocumentPdf::dispatch($document);
        GenerateDocumentPdf::dispatch($document);

        Queue::assertPushedTimes(GenerateDocumentPdf::class, 1);
    }
}
