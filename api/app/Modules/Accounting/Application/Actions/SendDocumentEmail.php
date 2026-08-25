<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Mail\DocumentShareMail;
use App\Modules\Accounting\Application\Jobs\GenerateDocumentPdf;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Services\DocumentShareService;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi d'un document comptable au contact client (issue #5225).
 *
 * 1. Garantit le PDF archivé (job GenerateDocumentPdf — idempotent) ;
 * 2. Crée le partage sécurisé (token + expiration) ;
 * 3. Envoie l'email (PDF en pièce jointe + lien portail) ;
 * 4. Marque sent_at et fait passer le document en `sent` (workflow #5223).
 */
final class SendDocumentEmail
{
    public function __construct(
        private readonly DocumentShareService $shareService,
    ) {}

    public function handle(AccountingDocument $document, string $email): string
    {
        GenerateDocumentPdf::dispatchSync($document);
        $document->refresh();

        $share = $this->shareService->createShare($document, $email);

        Mail::to($email)->send(new DocumentShareMail(
            share: $share,
            pdfPath: $document->pdf_path,
            pdfName: $document->type.'-'.$document->number.'.pdf',
        ));

        $document->update(['sent_at' => now()]);

        // Transition minimale draft → sent (les règles complètes du cycle de
        // vie sont portées par DocumentWorkflowService, issue #5223).
        if ($document->status === DocumentStatus::Draft->value) {
            $document->update(['status' => DocumentStatus::Sent->value]);
        }

        return $share->share_token;
    }
}
