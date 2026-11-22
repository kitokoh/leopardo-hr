<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Jobs\GenerateDocumentPdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Garantit qu'un document comptable possède un PDF archivé (issue #9799).
 *
 * Le job `GenerateDocumentPdf` n'était dispatché que par `SendDocumentEmail` :
 * un document partagé via `POST /accounting/documents/{document}/share` puis
 * téléchargé immédiatement renvoyait `404 DOCUMENT_PDF_NOT_READY`, parce que
 * rien n'avait déclenché la génération. Le lien que le vendeur venait d'envoyer
 * à son client était mort à la naissance.
 *
 * Ce service comble le trou sans dupliquer la logique de rendu : il réutilise
 * le job existant en mode synchrone (`dispatchSync`), exactement comme
 * `SendDocumentEmail` le fait déjà. Le job reste la seule implémentation —
 * locale, chemin de destination, idempotence et retry y vivent.
 *
 * Deux points d'appel :
 *  - `AccountingDocumentController::share()` — endpoint authentifié, donc la
 *    génération se fait au moment où le vendeur crée le lien ;
 *  - `PublicDocumentShareController::download()` — repli paresseux pour les
 *    partages créés avant ce correctif (ou dont le PDF a été purgé).
 *
 * Le retour booléen est volontaire : l'appelant public doit pouvoir répondre
 * `404 DOCUMENT_PDF_NOT_READY` plutôt qu'une 500 si le rendu échoue réellement.
 */
final class DocumentPdfArchiveService
{
    /**
     * @return bool vrai si un PDF exploitable est présent sur le disque après l'appel
     */
    public function ensure(AccountingDocument $document): bool
    {
        if ($this->hasPdf($document)) {
            return true;
        }

        try {
            // dispatchSync exécute le job dans le processus courant, middleware
            // `EnsureTenantContext` compris : le search_path et `current_company`
            // sont posés depuis `$document->company_id`, ce qui rend l'appel sûr
            // depuis la route publique (aucun contexte tenant ambiant).
            GenerateDocumentPdf::dispatchSync($document);
        } catch (Throwable $exception) {
            // Un échec de rendu n'est pas une erreur serveur du point de vue du
            // visiteur : le document existe, son PDF n'est pas disponible. On
            // journalise pour l'exploitant et on laisse l'appelant décider du
            // statut HTTP (404 localisé côté portail public).
            Log::error('accounting.document_pdf_on_demand_failed', [
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $document->refresh();

        return $this->hasPdf($document);
    }

    private function hasPdf(AccountingDocument $document): bool
    {
        return $document->pdf_path !== null
            && Storage::disk(GenerateDocumentPdf::DISK)->exists($document->pdf_path);
    }
}
