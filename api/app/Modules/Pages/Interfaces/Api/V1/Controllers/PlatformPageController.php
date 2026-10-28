<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Pages\Application\Actions\RecordPageEvidenceAccessAction;
use App\Modules\Pages\Application\Actions\SuspendPageAction;
use App\Modules\Pages\Application\Actions\UnsuspendPageAction;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageCustomDomain;
use App\Modules\Pages\Domain\Models\PageModerationAudit;
use App\Modules\Pages\Domain\Models\PageVerificationEvidence;
use App\Modules\Pages\Domain\Models\PageVerificationRequest;
use App\Modules\Pages\Domain\Services\PageVerificationService;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Modération plateforme des Pages (BC PAGES, #8729).
 *
 * ## Placement : le code reste dans le module, la route est plateforme
 *
 * Ces contrôleurs vivent dans `Modules/Pages` et sont routés sous le groupe
 * `/platform` déjà protégé (`auth:super_admin_api` + `platform.permission`).
 * C'est le précédent établi par `PlatformFundraisingSettingsController`
 * (#8441) et `PlatformTradeController` (#8485) : la surface d'administration
 * est plateforme, mais le code reste chez le module métier, ce qui satisfait
 * la garde d'isolation sans affaiblir l'allowlist.
 *
 * ## Pourquoi `withinTenant()` et pas une requête cross-tenant
 *
 * Les tables `pages`, `page_verification_*` et `page_custom_domains` vivent
 * dans le schéma du tenant. Un superadmin n'a pas de `company_id` : sans
 * `withinTenant()`, le scope global `BelongsToCompany` lèverait
 * `TenantContextMissingException` (fail-closed, #3727). On entre donc
 * explicitement dans le tenant ciblé — et on en ressort par le `finally` du
 * manager, même en cas d'exception.
 *
 * ## Ce que la surface n'expose PAS
 *
 * Aucun contenu de pièce justificative. `evidence()` renvoie des
 * **métadonnées** (type, taille, empreinte, échéance de conservation) et une
 * URL **signée à courte durée** ; l'accès est tracé dans le journal immuable.
 * Un superadmin ne doit pas pouvoir aspirer les baux de ses clients en
 * silence.
 */
final class PlatformPageController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly SuspendPageAction $suspendPage,
        private readonly UnsuspendPageAction $unsuspendPage,
        private readonly RecordPageEvidenceAccessAction $recordEvidenceAccess,
        private readonly PageVerificationService $verification,
    ) {}

    /**
     * `GET /platform/pages` — recherche et filtres cross-tenant.
     *
     * Filtres : `company_id`, `site_id`, `status`, `verification_status`,
     * `suspended` (bool), `has_domain` (bool), `q` (slug).
     *
     * La recherche cross-tenant est **bornée** : sans `company_id`, on
     * parcourt les tenants un par un et on agrège, avec une limite dure. Un
     * `SELECT` global sur toutes les tables `pages` de tous les schémas
     * n'existe pas — et c'est voulu : il n'y a pas de table partagée à
     * interroger, donc pas de requête unique à oublier de borner.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 25)));

        $companyIds = $request->filled('company_id')
            ? [(string) $request->input('company_id')]
            : $this->tenantIdsWithPages();

        $rows = [];

        foreach ($companyIds as $companyId) {
            $company = PlatformCompanyLookup::findOrFail($companyId);

            $rows = array_merge($rows, $this->tenantManager->withinTenant(
                $company,
                fn (): array => $this->queryFor($request)->limit($perPage)->get()
                    ->map(fn (Page $page): array => $this->summarize($page, $companyId))
                    ->all(),
            ));
        }

        usort($rows, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return response()->json([
            'data' => array_slice($rows, 0, $perPage),
            'meta' => [
                'tenants_scanned' => count($companyIds),
                'returned' => min(count($rows), $perPage),
            ],
        ]);
    }

    /**
     * `GET /platform/pages/{company}/{page}` — fiche complète de modération.
     */
    public function show(string $company, int $page): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($page, $company): JsonResponse {
            /** @var Page $model */
            $model = Page::query()->findOrFail($page);

            return response()->json([
                'data' => $this->summarize($model, $company) + [
                    'seo' => $model->seo,
                    'legal' => $model->legal,
                    'custom_domain' => $model->custom_domain,
                    'verification_requests' => PageVerificationRequest::query()
                        ->where('page_id', $model->id)
                        ->orderByDesc('id')
                        ->limit(20)
                        ->get()
                        ->map(static fn (PageVerificationRequest $r): array => [
                            'id' => $r->id,
                            'method' => $r->method,
                            'status' => $r->status,
                            'submitted_at' => $r->submitted_at?->toIso8601String(),
                            'decided_at' => $r->decided_at?->toIso8601String(),
                            'decided_by' => $r->decided_by,
                            'decision_reason' => $r->decision_reason,
                            'evidence_count' => $r->evidence_count,
                        ])
                        ->all(),
                    'domains' => PageCustomDomain::query()
                        ->where('page_id', $model->id)
                        ->orderByDesc('id')
                        ->get()
                        ->map(static fn (PageCustomDomain $d): array => [
                            'id' => $d->id,
                            'host' => $d->host,
                            'status' => $d->status,
                            'certificate_status' => $d->certificate_status,
                            'certificate_expires_at' => $d->certificate_expires_at?->toIso8601String(),
                            'verified_at' => $d->verified_at?->toIso8601String(),
                            'removed_at' => $d->removed_at?->toIso8601String(),
                            'last_error' => $d->last_error,
                        ])
                        ->all(),
                    'moderation_audits' => PageModerationAudit::query()
                        ->where('page_id', $model->id)
                        ->orderByDesc('id')
                        ->limit(50)
                        ->get()
                        ->map(static fn (PageModerationAudit $a): array => [
                            'id' => $a->id,
                            'action' => $a->action,
                            'reason' => $a->reason,
                            'actor_id' => $a->actor_id,
                            'actor_role' => $a->actor_role,
                            'before' => $a->before,
                            'after' => $a->after,
                            'created_at' => $a->created_at->toIso8601String(),
                        ])
                        ->all(),
                ],
            ]);
        });
    }

    /**
     * `POST /platform/pages/{company}/{page}/suspend` — motif obligatoire.
     */
    public function suspend(Request $request, string $company, int $page): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', Page::SUSPENSION_REASONS)],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);
        $actor = $this->actor($request);

        return $this->tenantManager->withinTenant($target, function () use ($page, $company, $validated, $actor, $request): JsonResponse {
            /** @var Page $model */
            $model = Page::query()->findOrFail($page);

            $this->suspendPage->execute($model, $actor['id'], $actor['role'], (string) $validated['reason'], $request);

            return response()->json(['data' => $this->summarize($model->refresh(), $company)]);
        });
    }

    /**
     * `POST /platform/pages/{company}/{page}/unsuspend`.
     */
    public function unsuspend(Request $request, string $company, int $page): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', PageVerificationRequest::DECISION_REASONS)],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);
        $actor = $this->actor($request);

        return $this->tenantManager->withinTenant($target, function () use ($page, $company, $validated, $actor, $request): JsonResponse {
            /** @var Page $model */
            $model = Page::query()->findOrFail($page);

            $this->unsuspendPage->execute($model, $actor['id'], $actor['role'], (string) $validated['reason'], $request);

            return response()->json(['data' => $this->summarize($model->refresh(), $company)]);
        });
    }

    /**
     * `POST /platform/pages/{company}/{page}/verification/{requestId}/decide`.
     *
     * `decision` ∈ {approve, reject, dispute, revoke}. Le motif est validé par
     * le service de domaine (`DECISION_REASONS`), pas recopié ici.
     */
    public function decideVerification(Request $request, string $company, int $page, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject,dispute,revoke'],
            'reason' => ['required', 'string', 'max:80'],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);
        $actor = $this->actor($request);

        return $this->tenantManager->withinTenant($target, function () use ($page, $requestId, $validated, $actor, $request): JsonResponse {
            /** @var PageVerificationRequest $verificationRequest */
            $verificationRequest = PageVerificationRequest::query()
                ->where('page_id', $page)
                ->findOrFail($requestId);

            $decision = (string) $validated['decision'];

            $decided = match ($decision) {
                'approve' => $this->verification->approve($verificationRequest, $actor['id'], (string) $validated['reason']),
                'reject' => $this->verification->reject($verificationRequest, $actor['id'], (string) $validated['reason']),
                'dispute' => $this->verification->dispute($verificationRequest, $actor['id'], (string) $validated['reason']),
                'revoke' => $this->verification->revoke($verificationRequest, $actor['id'], (string) $validated['reason']),
                default => throw new \LogicException('Decision hors allowlist acceptee par la validation : '.$decision),
            };

            // `recordEvidenceAccess()` exige une Page non nullable, or
            // `$decided->page` est une relation nullable (Page supprimee
            // entre-temps). On relit la Page par sa cle : une garantie
            // verifiable plutot qu'un `?->` qui sauterait l'audit en silence.

            $auditedPage = Page::query()->findOrFail($decided->page_id);

            // Le service de domaine écrit déjà son audit métier ; on ajoute la
            // ligne de modération pour que la fiche plateforme montre la
            // décision au même endroit que les suspensions.
            $this->recordEvidenceAccess->execute(
                $auditedPage,
                $actor['id'],
                $actor['role'],
                (int) $decided->id,
                $request,
            );

            return response()->json([
                'data' => [
                    'id' => $decided->id,
                    'status' => $decided->status,
                    'decision_reason' => $decided->decision_reason,
                    'decided_at' => $decided->decided_at?->toIso8601String(),
                ],
            ]);
        });
    }

    /**
     * `GET /platform/pages/{company}/{page}/evidence/{evidenceId}` — métadonnées
     * + URL signée courte. L'accès est tracé.
     */
    public function evidence(Request $request, string $company, int $page, int $evidenceId): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $actor = $this->actor($request);

        return $this->tenantManager->withinTenant($target, function () use ($page, $evidenceId, $actor, $request): JsonResponse {
            /** @var PageVerificationEvidence $evidence */
            $evidence = PageVerificationEvidence::query()
                ->whereIn('request_id', PageVerificationRequest::query()->where('page_id', $page)->select('id'))
                ->findOrFail($evidenceId);

            $this->recordEvidenceAccess->execute(
                Page::query()->findOrFail($page),
                $actor['id'],
                $actor['role'],
                (int) $evidence->id,
                $request,
            );

            // URL signée de courte durée : la pièce n'est jamais servie depuis
            // un chemin public, et le lien expire avant d'être partageable.
            $url = Storage::disk($evidence->storage_disc)->temporaryUrl(
                $evidence->storage_path,
                now()->addMinutes(5),
            );

            return response()->json([
                'data' => [
                    'id' => $evidence->id,
                    'kind' => $evidence->kind,
                    'mime_type' => $evidence->mime_type,
                    'size_bytes' => $evidence->size_bytes,
                    'content_hash' => $evidence->content_hash,
                    'retention_until' => $evidence->retention_until->toIso8601String(),
                    'signed_url' => $url,
                    'expires_in_seconds' => 300,
                ],
            ]);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Page>
     */
    private function queryFor(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Page::query();

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', (string) $request->input('verification_status'));
        }

        if ($request->has('suspended')) {
            $request->boolean('suspended')
                ? $query->whereNotNull('suspended_at')
                : $query->whereNull('suspended_at');
        }

        if ($request->has('has_domain')) {
            $request->boolean('has_domain')
                ? $query->whereNotNull('custom_domain')
                : $query->whereNull('custom_domain');
        }

        if ($request->filled('q')) {
            $query->where('slug', 'ilike', '%'.(string) $request->input('q').'%');
        }

        return $query->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Page $page, string $companyId): array
    {
        return [
            'id' => $page->id,
            'company_id' => $companyId,
            'site_id' => $page->site_id,
            'slug' => $page->slug,
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toIso8601String(),
            'verification_status' => $page->verification_status->value,
            'verified_at' => $page->verified_at?->toIso8601String(),
            'suspended_at' => $page->suspended_at?->toIso8601String(),
            'suspension_reason' => $page->suspension_reason,
            'custom_domain' => $page->custom_domain,
        ];
    }

    /**
     * Identité de l'acteur plateforme, telle qu'elle sera écrite dans l'audit.
     *
     * @return array{id: string, role: string|null}
     */
    private function actor(Request $request): array
    {
        /** @var SuperAdmin $admin */
        $admin = $request->user('super_admin_api');

        return [
            'id' => 'platform:'.$admin->id,
            'role' => $admin->platformRole()->value,
        ];
    }

    /**
     * Tenants ayant au moins une Page, bornés.
     *
     * @return list<string>
     */
    private function tenantIdsWithPages(): array
    {
        /** @var list<object{id: string}> $rows */
        $rows = DB::select(
            'SELECT id FROM public.companies ORDER BY created_at DESC LIMIT 200'
        );

        return array_map(static fn (object $row): string => (string) $row->id, $rows);
    }
}
