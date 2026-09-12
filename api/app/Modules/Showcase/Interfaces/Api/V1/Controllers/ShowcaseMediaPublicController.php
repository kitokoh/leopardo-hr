<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — service public d'un média de vitrine.
 *
 * Route isolée SANS auth (groupe `throttle:shop-public`), tenant résolu par
 * le slug public (`companies.slug` en schéma public) puis lecture dans le
 * schéma tenant via TenantManager::withinTenant. Un média n'est servi que
 * si la vitrine est PUBLIÉE — le brouillon n'est joignable que par son
 * jeton d'aperçu privé (`?token=`, même contrat que GET /public/vitrine/{slug}).
 *
 * Le binaire provient du disk privé `local` (hors webroot) : aucune donnée
 * interne n'est exposée (pas de JSON, pas de `company_id`/`disk`/`path`),
 * et les en-têtes de cache/immutabilité sont posés explicitement :
 *  - publié : `Cache-Control: public, max-age=86400` + `Last-Modified`
 *    (requête conditionnelle → 304 sans relecture du fichier) ;
 *  - aperçu : `Cache-Control: private, no-store` + `X-Robots-Tag: noindex` ;
 *  - SVG : `Content-Security-Policy` sandbox (défense anti-XSS en profondeur)
 *    et `X-Content-Type-Options: nosniff` sur toutes les réponses.
 */
final class ShowcaseMediaPublicController extends Controller
{
    private const CACHE_SECONDS = 86400;

    public function __construct(private readonly TenantManager $tenantManager) {}

    public function show(Request $request, string $slug, int $media): Response|StreamedResponse
    {
        $providedToken = $request->query('token');
        $providedToken = is_string($providedToken) && $providedToken !== '' ? $providedToken : null;

        $resolved = $this->resolveMedia($slug, $media, $providedToken);

        if ($resolved === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        [$record, $isPreview] = $resolved;

        $response = Storage::disk($record->disk)->response($record->path, $record->file_name, [
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $isPreview
                ? 'private, no-store, max-age=0'
                : 'public, max-age='.self::CACHE_SECONDS,
        ]);

        if ($record->extension === 'svg') {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }

        if ($isPreview) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } else {
            $response->setLastModified($record->updated_at);

            if ($response->isNotModified($request)) {
                $response->setNotModified();
            }
        }

        return $response;
    }

    /**
     * Résout le média d'une vitrine publiée (ou d'un aperçu à jeton valide).
     *
     * @return array{0: ShowcaseMedia, 1: bool}|null
     */
    private function resolveMedia(string $slug, int $mediaId, ?string $providedToken): ?array
    {
        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('status', '!=', 'suspended')
            ->first();

        if (! $company instanceof Company) {
            return null;
        }

        return $this->tenantManager->withinTenant($company, function () use ($company, $slug, $mediaId, $providedToken): ?array {
            /** @var CompanyShowcase|null $showcase */
            $showcase = CompanyShowcase::query()
                ->where('slug', $slug)
                ->where('company_id', $company->id)
                ->first();

            if (! $showcase instanceof CompanyShowcase) {
                return null;
            }

            $isPublished = $showcase->status === CompanyShowcaseStatus::Published;
            $previewAuthorized = ! $isPublished
                && $providedToken !== null
                && $showcase->preview_token !== null
                && hash_equals($showcase->preview_token, $providedToken);

            if (! $isPublished && ! $previewAuthorized) {
                return null;
            }

            /** @var ShowcaseMedia|null $media */
            $media = ShowcaseMedia::query()
                ->whereKey($mediaId)
                ->where('showcase_id', $showcase->id)
                ->first();

            if (! $media instanceof ShowcaseMedia) {
                return null;
            }

            // Un jeton fourni sur une vitrine publiée reste un aperçu : la
            // réponse n'est jamais servie depuis un cache partagé.
            return [$media, $providedToken !== null];
        });
    }
}
