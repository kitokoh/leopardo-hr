<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Application\Jobs\GenerateDocumentPdf;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use App\Modules\Accounting\Domain\Models\DocumentShareLookup;
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

        $this->auditAccess($share, 'accounting.share.info');

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

        $this->auditAccess($share, 'accounting.share.download');

        $filename = $document->type.'-'.$document->number.'.pdf';

        return Storage::disk(GenerateDocumentPdf::DISK)->download($document->pdf_path, $filename);
    }

    /**
     * Résout le token en O(1) via le lookup public token → company (issue
     * #5428) : une requête publique + une bascule unique vers le tenant de la
     * compagnie du partage — plus d'itération de toutes les entreprises
     * actives (ancien comportement O(N) : N bascules de search_path par
     * requête publique, risque d'oracle de timing).
     */
    private function resolveShare(string $token): ?AccountingDocumentShare
    {
        $lookup = DocumentShareLookup::query()->where('share_token', $token)->first();

        if ($lookup === null) {
            return null;
        }

        /** @var Company|null $company */
        $company = Company::query()->where('id', $lookup->company_id)->first();

        if ($company === null) {
            return null;
        }

        return app(TenantManager::class)
            ->withinTenant($company, fn (): ?AccountingDocumentShare => $this->shareService->resolve($token));
    }

    /**
     * Trace un accès public au portail (issue #5429) — RGPD : qui a consulté
     * / téléchargé quel document partagé, quand, depuis quelle IP. Écrit dans
     * le tenant de la compagnie du partage (user_id null : accès non authentifié).
     */
    private function auditAccess(AccountingDocumentShare $share, string $action): void
    {
        $company = Company::query()->where('id', $share->company_id)->first();

        if ($company === null) {
            return;
        }

        app(TenantManager::class)->withinTenant($company, function () use ($share, $action): void {
            AuditLog::create([
                'company_id' => $share->company_id,
                'user_id' => null,
                'action' => $action,
                'auditable_type' => $share->getMorphClass(),
                'auditable_id' => $share->id,
                'old_values' => [],
                'new_values' => [],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'metadata' => ['share_token' => $share->share_token],
            ]);
        });
    }
}
