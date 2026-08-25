<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Application\Jobs\GenerateDocumentPdf;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use App\Modules\Accounting\Infrastructure\Services\DocumentShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portail client sécurisé des documents comptables (issue #5225).
 *
 * Endpoints PUBLICS (le token de partage est la credential — pas d'auth
 * Sanctum) : consultation limitée au document partagé (RGPD) et
 * téléchargement du PDF. Pattern CabinetShare (#1817) : résolution du token
 * dans le contexte tenant de chaque entreprise active.
 */
final class PublicDocumentShareController
{
    public function __construct(private readonly DocumentShareService $shareService) {}

    public function info(string $token): JsonResponse
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            abort(404, 'DOCUMENT_SHARE_NOT_FOUND');
        }

        /** @var AccountingDocument $document */
        $document = $share->document;

        return response()->json([
            'data' => [
                'number' => $document->number,
                'type' => $document->type,
                'type_label' => __('accounting.document_type_'.$document->type),
                'status' => $document->status,
                'issue_date' => $document->issue_date->toDateString(),
                'currency' => $document->currency,
                'total_ttc' => round((float) $document->total_ttc, 2),
                'expires_at' => $share->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function download(string $token): StreamedResponse
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            abort(404, 'DOCUMENT_SHARE_NOT_FOUND');
        }

        /** @var AccountingDocument $document */
        $document = $share->document;

        if ($document->pdf_path === null || ! Storage::disk(GenerateDocumentPdf::DISK)->exists($document->pdf_path)) {
            abort(404, 'DOCUMENT_PDF_NOT_READY');
        }

        $filename = $document->type.'-'.$document->number.'.pdf';

        return Storage::disk(GenerateDocumentPdf::DISK)->download($document->pdf_path, $filename);
    }

    /**
     * Résout le token dans le contexte tenant de chaque entreprise active
     * (le scope global BelongsToCompany exige current_company).
     */
    private function resolveShare(string $token): ?AccountingDocumentShare
    {
        $tenantManager = app(TenantManager::class);
        $companies = Company::query()->where('status', 'active')->orderBy('id')->get();

        foreach ($companies as $company) {
            $share = $tenantManager->withinTenant($company, fn (): ?AccountingDocumentShare => $this->shareService->resolve($token));

            if ($share !== null) {
                return $share;
            }
        }

        return null;
    }
}
