<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
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

        $this->auditAccess($share, 'accounting.share.info');

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
        ])->header('Referrer-Policy', 'no-referrer');
    }

    public function download(string $token): StreamedResponse
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            abort(404, 'DOCUMENT_SHARE_NOT_FOUND');
        }

        $this->auditAccess($share, 'accounting.share.download');

        /** @var AccountingDocument $document */
        $document = $share->document;

        if ($document->pdf_path === null || ! Storage::disk(GenerateDocumentPdf::DISK)->exists($document->pdf_path)) {
            abort(404, 'DOCUMENT_PDF_NOT_READY');
        }

        $filename = $document->type.'-'.$document->number.'.pdf';

        // #5521 — no-referrer strict : le token ne doit pas fuiter via Referer
        // lors du téléchargement (règles navigateur + défense en profondeur).
        // NB : Storage::download() renvoie un StreamedResponse Symfony — la
        // macro Laravel ->header() n'existe pas dessus (500 en prod) ; on
        // pose le header via HeaderBag (#5522 — fix main 2026-08-25).
        $response = Storage::disk(GenerateDocumentPdf::DISK)->download($document->pdf_path, $filename);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    /**
     * Résolution O(1) du token de partage (issue #5428).
     *
     * Le token (64 caractères aléatoires, indexé unique) EST la credential :
     * les routes publiques n'ont ni auth ni TenantMiddleware, donc le scope
     * global BelongsToCompany est inactif et le search_path par défaut
     * (`shared_tenants,public`) couvre tous les tenants à schéma partagé —
     * une seule requête suffit, sans itération des compagnies (perf O(N) +
     * risque d'oracle de timing supprimé).
     *
     * Fallback : les tenants legacy à schéma dédié (`schema_name` propre,
     * création verrouillée #COMPANY_SCHEMA_MODE_LOCKED) ne sont pas visibles
     * depuis le search_path par défaut — on ne les itère QUE sur échec du
     * lookup direct (rare : token invalide ou partage d'un tenant à schéma).
     */
    private function resolveShare(string $token): ?AccountingDocumentShare
    {
        $share = AccountingDocumentShare::query()
            ->withoutGlobalScope('company')
            ->with(['document' => fn ($query) => $query->withoutGlobalScope('company')])
            ->where('share_token', $token)
            ->first();

        if ($share !== null) {
            return $share->isExpired() ? null : $share;
        }

        $tenantManager = app(TenantManager::class);
        $schemaTenants = Company::query()
            ->where('status', 'active')
            ->whereNotNull('schema_name')
            ->where('schema_name', '!=', 'shared_tenants')
            ->orderBy('id')
            ->get();

        foreach ($schemaTenants as $company) {
            $share = $tenantManager->withinTenant($company, fn (): ?AccountingDocumentShare => $this->shareService->resolve($token));

            if ($share !== null) {
                return $share;
            }
        }

        return null;
    }

    /**
     * Trace un accès public au portail (issue #5429/#5520) — RGPD : qui a
     * consulté / téléchargé quel document partagé, quand, depuis quelle IP.
     *
     * Utilise l'API unifiée AuditLog::record() (#5439) pour garantir que
     * `module` et `request_id` (corrélation) sont toujours renseignés.
     * user_id = null : accès non authentifié (token de partage = credential).
     *
     * @param  string  $action  Action complète préfixée module : 'accounting.share.info' | 'accounting.share.download'
     */
    private function auditAccess(AccountingDocumentShare $share, string $action): void
    {
        $company = Company::query()->where('id', $share->company_id)->first();

        if ($company === null) {
            return;
        }

        app(TenantManager::class)->withinTenant($company, function () use ($share, $action): void {
            AuditLog::record(
                module: 'accounting',
                action: $action,
                subject: $share,
                actor: null,
                metadata: ['share_token' => $share->share_token],
            );
        });
    }
}
