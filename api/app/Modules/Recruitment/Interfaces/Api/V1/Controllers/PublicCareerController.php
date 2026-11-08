<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExternalJobOfferResource;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Application\Services\PublicJobCatalog;
use App\Modules\Recruitment\Domain\Models\ExternalJobOffer;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Shared\Support\TenantCache;
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
 *
 * BC-10 (#9100) — le portail agrège désormais DEUX sources : les offres du
 * tenant et les offres importées par la plateforme depuis les agrégateurs
 * (`public.external_job_offers`). La fusion est faite par `PublicJobCatalog`,
 * jamais par une jointure cross-schéma implicite.
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §3.1, §5.1
 */
class PublicCareerController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PublicJobCatalog $catalog,
    ) {}

    /**
     * GET /api/v1/public/careers/{companySlug}
     *
     * List the active (published, not yet closed) job postings of a company,
     * plus the platform-imported offers matching the company's country.
     */
    public function index(Request $request, string $companySlug): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);
        $filters = $this->filters($request);

        $tenantJobs = $this->tenantManager->withinTenant(
            $company,
            fn () => $this->catalog->tenantOffers($company, $filters),
        );

        $externalOffers = $this->catalog->externalOffers($filters, $this->countryOf($company));

        return new JsonResponse([
            'data' => [
                ...JobPostingResource::collection($tenantJobs)->resolve(),
                ...ExternalJobOfferResource::collection($externalOffers)->resolve(),
            ],
            'meta' => [
                'company' => $this->companyPayload($company),
                'counts' => [
                    'company' => $tenantJobs->count(),
                    'external' => $externalOffers->count(),
                    'total' => $tenantJobs->count() + $externalOffers->count(),
                ],
                'filters' => $filters,
            ],
        ]);
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
     * Google Jobs / Indeed compatible XML feed of the company's published job
     * postings, plus the platform-imported offers for the same country.
     *
     * @see https://developers.google.com/search/docs/appearance/structured-data/job-posting
     */
    public function feed(string $companySlug): Response
    {
        $company = $this->resolveCompany($companySlug);

        // #8058 — tenant-cache:via-helper — clé construite par TenantCache::keyFor (préfixe company_id systématique)
        $xml = Cache::remember(
            TenantCache::keyFor((string) $company->id, 'recruitment:feed'),
            now()->addMinutes(15),
            fn (): string => $this->buildXmlFeed($company),
        );

        return response($xml, SymfonyResponse::HTTP_OK)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function buildXmlFeed(Company $company): string
    {
        $jobs = $this->tenantManager->withinTenant(
            $company,
            fn () => $this->catalog->tenantOffers($company, []),
        );

        $external = $this->catalog->externalOffers([], $this->countryOf($company));

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
            // Google Jobs exige `expirationDate` : sans elle, l'annonce peut
            // rester indexée après sa clôture. On borne par défaut à 30 jours.
            $entry->addChild('expirationDate', ($job->closes_at ?? $job->published_at?->copy()->addDays(30) ?? now()->addDays(30))->toRfc2822String());
            $entry->addChild('remote', $this->mapRemote($job->remote_policy));

            if ($job->salary_range_min || $job->salary_range_max) {
                $entry->addChild('salary', $this->cdata(sprintf(
                    '%s-%s %s',
                    rtrim(rtrim((string) $job->salary_range_min, '0'), '.'),
                    rtrim(rtrim((string) $job->salary_range_max, '0'), '.'),
                    $job->currency
                )));
            }
        }

        foreach ($external as $offer) {
            $entry = $xml->addChild('job');
            $entry->addChild('title', $this->cdata($offer->title));
            $entry->addChild('date', $offer->published_at?->toRfc2822String() ?? $offer->created_at?->toRfc2822String());
            // Préfixe `ext-` : un identifiant d'offre agrégée ne doit jamais
            // pouvoir entrer en collision avec un id de `job_postings`.
            $entry->addChild('referencenumber', 'ext-'.$offer->id);
            $entry->addChild('url', $this->cdata($offer->apply_url));
            $entry->addChild('company', $this->cdata($offer->company_name));
            $entry->addChild('city', $this->cdata((string) $offer->location));
            $entry->addChild('country', $this->cdata((string) ($offer->country ?? $company->country)));
            $entry->addChild('description', $this->cdata((string) $offer->description));
            $entry->addChild('jobtype', $this->cdata($this->mapContractType($offer->contract_type)));
            $entry->addChild('expirationDate', ($offer->expires_at ?? now()->addDays(30))->toRfc2822String());
            $entry->addChild('remote', $this->mapRemote($offer->remote_policy));

            if ($offer->salary_min || $offer->salary_max) {
                $entry->addChild('salary', $this->cdata(sprintf(
                    '%s-%s %s',
                    rtrim(rtrim((string) $offer->salary_min, '0'), '.'),
                    rtrim(rtrim((string) $offer->salary_max, '0'), '.'),
                    (string) $offer->currency
                )));
            }
        }

        $formatted = $xml->asXML();

        return $formatted !== false ? $formatted : '<?xml version="1.0" encoding="UTF-8"?><source></source>';
    }

    /**
     * Filtres publics communs à la liste et au flux.
     *
     * @return array{location: string|null, contract_type: string|null, remote_policy: string|null, q: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'location' => $request->filled('location') ? (string) $request->string('location') : null,
            'contract_type' => $request->filled('contract_type') ? (string) $request->string('contract_type') : null,
            'remote_policy' => $request->filled('remote_policy') ? (string) $request->string('remote_policy') : null,
            'q' => $request->filled('q') ? (string) $request->string('q') : null,
        ];
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

    private function mapRemote(?string $remotePolicy): string
    {
        return match ($remotePolicy) {
            'remote' => 'true',
            'hybrid' => 'true',
            default => 'false',
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

    private function countryOf(Company $company): ?string
    {
        $country = $company->getAttribute('country');

        return is_string($country) && $country !== '' ? strtoupper($country) : null;
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
