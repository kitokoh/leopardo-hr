<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Application\Actions\PublishPlatformJobPostingAction;
use App\Modules\Recruitment\Application\Services\PublicJobCatalog;
use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-10 RECRUITMENT — publication d'offres depuis la console plateforme.
 *
 * Routes (toutes sous `platform.permission:*`, cf.
 * routes/modules/recruitment_platform.php) :
 *
 *   GET  /api/v1/platform/recruitment/jobs                    → recruitment.view
 *   POST /api/v1/platform/recruitment/jobs                    → recruitment.manage
 *   POST /api/v1/platform/recruitment/jobs/{id}/publish       → recruitment.manage
 *   POST /api/v1/platform/recruitment/jobs/{id}/close         → recruitment.manage
 *   GET  /api/v1/platform/recruitment/offers                  → recruitment.view
 *
 * Toute écriture se fait DANS le contexte du tenant ciblé
 * (`TenantManager::withinTenant`), jamais par un `company_id` mass-assigné
 * (#7646) : c'est ce qui garantit que l'offre atterrit bien chez le client
 * visé et nulle part ailleurs.
 */
final class PlatformJobPostingController
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PublicJobCatalog $catalog,
    ) {}

    /**
     * Offres de tous les tenants, pour la vue plateforme.
     *
     * Lecture cross-tenant assumée : elle est derrière
     * `platform.permission:recruitment.view` et utilise le scope dédié
     * `crossTenantForPlatformAdmin()` (#7960), jamais un `withoutGlobalScope`
     * brut (garde CI `check-without-global-scope.sh`).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,closed,archived'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        // Les offres vivent dans le schéma de CHAQUE tenant : on parcourt donc
        // les entreprises et on interroge chacune dans son contexte. Un
        // `JobPosting::crossTenantForPlatformAdmin()` ne suffirait pas — le
        // search_path ne pointerait que sur un schéma.
        $companies = Company::query()
            ->when(
                ! empty($validated['company_id']),
                fn ($q) => $q->where('id', $validated['company_id']),
            )
            ->orderBy('name')
            ->limit(200)
            ->get();

        $rows = [];

        foreach ($companies as $company) {
            $jobs = $this->tenantManager->withinTenant($company, function () use ($company, $validated): array {
                return JobPosting::query()
                    ->where('company_id', $company->id)
                    ->when(
                        ! empty($validated['status']),
                        fn ($q) => $q->where('status', $validated['status']),
                    )
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (JobPosting $job): array => [
                        ...(new JobPostingResource($job))->resolve(),
                        'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
                    ])
                    ->all();
            });

            $rows = [...$rows, ...$jobs];
        }

        return new JsonResponse([
            'data' => array_slice($rows, 0, $perPage),
            'meta' => ['total' => count($rows), 'companies_scanned' => $companies->count()],
        ]);
    }

    /**
     * Crée une offre pour un tenant ciblé, depuis la plateforme.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'string', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'location' => ['nullable', 'string', 'max:200'],
            'remote_policy' => ['nullable', 'in:onsite,hybrid,remote'],
            'contract_type' => ['nullable', 'in:cdi,cdd,stage,freelance'],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'currency' => ['nullable', 'string', 'size:3'],
            'skills_required' => ['nullable', 'array'],
            'closes_at' => ['nullable', 'date', 'after:today'],
            'publish' => ['nullable', 'boolean'],
        ]);

        /** @var Company|null $company */
        $company = Company::query()->find($validated['company_id']);

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'Entreprise introuvable.',
            ], 404);
        }

        $actorId = $this->platformAdminId($request);
        $shouldPublish = (bool) ($validated['publish'] ?? false);

        $job = $this->tenantManager->withinTenant($company, function () use ($company, $validated, $actorId, $shouldPublish): JobPosting {
            $job = JobPosting::create([
                'company_id' => $company->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'remote_policy' => $validated['remote_policy'] ?? 'onsite',
                'contract_type' => $validated['contract_type'] ?? 'cdi',
                'salary_range_min' => $validated['salary_range_min'] ?? null,
                'salary_range_max' => $validated['salary_range_max'] ?? null,
                'currency' => $validated['currency'] ?? 'DZD',
                'skills_required' => $validated['skills_required'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'status' => 'draft',
                'published_by_platform' => true,
                'platform_created_by' => $actorId,
            ]);

            if ($shouldPublish) {
                $job = app(PublishPlatformJobPostingAction::class)->execute($job, (int) $actorId);
            }

            return $job;
        });

        $this->audit('recruitment.platform_job.created', $company, $job, $actorId, [
            'published' => $shouldPublish,
        ]);

        return (new JobPostingResource($job))->response()->setStatusCode(201);
    }

    /**
     * Publie une offre existante depuis la plateforme.
     */
    public function publish(Request $request, string $companyId, int $jobPosting): JsonResponse
    {
        return $this->transition($request, $companyId, $jobPosting, 'publish');
    }

    /**
     * Clôture une offre depuis la plateforme.
     */
    public function close(Request $request, string $companyId, int $jobPosting): JsonResponse
    {
        return $this->transition($request, $companyId, $jobPosting, 'close');
    }

    /**
     * Offres agrégées importées, visibles sur le portail public.
     */
    public function offers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:200'],
            'contract_type' => ['nullable', 'in:cdi,cdd,stage,freelance'],
            'remote_policy' => ['nullable', 'in:onsite,hybrid,remote'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->catalog->paginateExternalOffers(
            $validated,
            (int) ($validated['per_page'] ?? 25),
        );

        return new JsonResponse([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function transition(Request $request, string $companyId, int $jobPosting, string $action): JsonResponse
    {
        /** @var Company|null $company */
        $company = Company::query()->find($companyId);

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'Entreprise introuvable.',
            ], 404);
        }

        $actorId = $this->platformAdminId($request);

        try {
            $job = $this->tenantManager->withinTenant($company, function () use ($company, $jobPosting, $action, $actorId): JobPosting {
                $job = JobPosting::query()
                    ->where('company_id', $company->id)
                    ->findOrFail($jobPosting);

                return $action === 'publish'
                    ? app(PublishPlatformJobPostingAction::class)->execute($job, (int) $actorId)
                    : app(\App\Modules\Recruitment\Application\Actions\CloseJobPostingAction::class)->execute($job);
            });
        } catch (JobPostingStateTransitionException $e) {
            return new JsonResponse([
                'error' => $e->translationKey,
                'message' => __('errors.'.$e->translationKey),
            ], 422);
        }

        $this->audit("recruitment.platform_job.{$action}ed", $company, $job, $actorId, []);

        return (new JobPostingResource($job))->response();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(string $action, Company $company, JobPosting $job, ?int $actorId, array $extra): void
    {
        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => $action,
            'module' => 'recruitment',
            'auditable_type' => JobPosting::class,
            'auditable_id' => (string) $job->getKey(),
            'old_values' => [],
            'new_values' => [
                'company_id' => $company->id,
                'status' => $job->status,
                ...$extra,
            ],
            'metadata' => ['actor_super_admin_id' => $actorId],
        ]);
    }

    private function platformAdminId(Request $request): ?int
    {
        $admin = $request->user('super_admin_api');

        if (! $admin instanceof SuperAdmin) {
            return null;
        }

        $id = $admin->getKey();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
