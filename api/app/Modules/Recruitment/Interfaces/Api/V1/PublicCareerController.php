<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * PublicCareerController — Unauthenticated "careers page" API.
 *
 * Exposes a company's *published* job postings to anonymous visitors (the
 * public careers portal / web widget) and a Google Jobs / Indeed compatible
 * XML feed. No Sanctum guard: the tenant is resolved from the `{companySlug}`
 * route segment instead of the authenticated user's company.
 */
class PublicCareerController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * GET /api/v1/public/careers/{companySlug}
     *
     * List the active (published, not yet closed) job postings of a company.
     */
    public function index(Request $request, string $companySlug): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);

        return $this->tenantManager->withinTenant($company, function () use ($request, $company): JsonResponse {
            $query = JobPosting::query()
                ->where('company_id', $company->id)
                ->published()
                ->where(function ($q): void {
                    $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                })
                ->with('department:id,name');

            if ($request->filled('location')) {
                $query->where('location', 'like', '%'.$request->string('location').'%');
            }

            if ($request->filled('contract_type')) {
                $query->where('contract_type', $request->input('contract_type'));
            }

            $jobs = $query->orderByDesc('published_at')->paginate($request->integer('per_page', 15));

            return JobPostingResource::collection($jobs)
                ->additional([
                    'meta' => [
                        'company' => $this->companyPayload($company),
                    ],
                ])
                ->response();
        });
    }

    /**
     * GET /api/v1/public/careers/{companySlug}/jobs/{jobPosting}
     *
     * Show a single published job posting.
     */
    public function show(string $companySlug, int $jobPosting): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);

        return $this->tenantManager->withinTenant($company, function () use ($company, $jobPosting): JsonResponse {
            $job = JobPosting::query()
                ->where('company_id', $company->id)
                ->published()
                ->with('department:id,name')
                ->findOrFail($jobPosting);

            return (new JobPostingResource($job))
                ->additional([
                    'meta' => [
                        'company' => $this->companyPayload($company),
                    ],
                ])
                ->response();
        });
    }

    /**
     * GET /api/v1/public/careers/{companySlug}/feed.xml
     *
     * Google Jobs / Indeed compatible XML feed of the company's published
     * job postings. See https://developers.google.com/search/docs/appearance/structured-data/job-posting
     * and Indeed's XML feed specification.
     */
    public function feed(string $companySlug): Response
    {
        $company = $this->resolveCompany($companySlug);

        $xml = Cache::remember(
            "recruitment:feed:{$company->id}",
            now()->addMinutes(15),
            fn (): string => $this->tenantManager->withinTenant($company, fn (): string => $this->buildXmlFeed($company))
        );

        return response($xml, SymfonyResponse::HTTP_OK)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function buildXmlFeed(Company $company): string
    {
        $jobs = JobPosting::query()
            ->where('company_id', $company->id)
            ->published()
            ->where(function ($q): void {
                $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            })
            ->orderByDesc('published_at')
            ->get();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><source></source>');
        $xml->addChild('publisher', $this->cdata($company->name));
        $xml->addChild('publisherurl', $this->cdata((string) config('app.url')));
        $xml->addChild('lastBuildDate', now()->toRfc2822String());

        foreach ($jobs as $job) {
            $entry = $xml->addChild('job');
            $entry->addChild('title', $this->cdata($job->title));
            $entry->addChild('date', $job->published_at?->toRfc2822String() ?? $job->created_at?->toRfc2822String());
            $entry->addChild('referencenumber', (string) $job->id);
            $entry->addChild('url', $this->cdata($this->publicJobUrl($company->slug, $job->id)));
            $entry->addChild('company', $this->cdata($company->name));
            $entry->addChild('city', $this->cdata((string) $job->location));
            $entry->addChild('country', $this->cdata((string) $company->country));
            $entry->addChild('description', $this->cdata((string) $job->description));
            $entry->addChild('jobtype', $this->cdata($this->mapContractType($job->contract_type)));
            if ($job->salary_range_min || $job->salary_range_max) {
                $entry->addChild('salary', $this->cdata(sprintf(
                    '%s-%s %s',
                    rtrim(rtrim((string) $job->salary_range_min, '0'), '.'),
                    rtrim(rtrim((string) $job->salary_range_max, '0'), '.'),
                    $job->currency
                )));
            }
        }

        $formatted = $xml->asXML();

        return $formatted !== false ? $formatted : '<?xml version="1.0" encoding="UTF-8"?><source></source>';
    }

    /**
     * SimpleXMLElement::addChild() does not escape special characters, so
     * every dynamic text node must be pre-escaped to keep the feed valid XML.
     */
    private function cdata(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function mapContractType(string $contractType): string
    {
        return match ($contractType) {
            'cdi' => 'FullTime',
            'cdd' => 'Contractor',
            'stage' => 'Intern',
            'freelance' => 'Temporary',
            default => 'FullTime',
        };
    }

    private function publicJobUrl(string $companySlug, int $jobId): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/')."/careers/{$companySlug}/jobs/{$jobId}";
    }

    private function resolveCompany(string $companySlug): Company
    {
        return Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->firstOrFail();
    }

    /**
     * Issue #1325 — the public careers page (front/web) needs the tenant's
     * display name, logo and brand colors to render itself, without
     * exposing any authenticated-only branding fields (logo_path/logo_disk
     * are internal storage details). Falls back to sane defaults when the
     * tenant never configured branding (see CompanyBrandingController).
     *
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        $metadata = $company->metadata ?? [];
        $branding = is_array($metadata['branding'] ?? null) ? $metadata['branding'] : [];

        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'display_name' => (is_string($branding['display_name'] ?? null) && $branding['display_name'] !== '')
                ? $branding['display_name']
                : $company->name,
            'logo_url' => is_string($branding['logo_url'] ?? null) ? $branding['logo_url'] : null,
            'primary_color' => (is_string($branding['primary_color'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding['primary_color']) === 1)
                ? strtoupper($branding['primary_color'])
                : '#10B981',
            'accent_color' => (is_string($branding['accent_color'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding['accent_color']) === 1)
                ? strtoupper($branding['accent_color'])
                : '#2563EB',
        ];
    }
}
