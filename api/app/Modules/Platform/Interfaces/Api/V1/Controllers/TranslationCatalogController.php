<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Support\I18nCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslationCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $versions = I18nCatalog::readVersions();
        } catch (\Throwable $e) {
            return $this->catalogUnavailable($e);
        }

        return response()->json([
            'success' => true,
            'data' => $versions,
        ]);
    }

    public function show(Request $request, ?string $locale = null): JsonResponse|Response
    {
        $requestedLocale = $locale ?? $request->query('locale') ?? $request->header('Accept-Language');
        $resolvedLocale = I18nCatalog::normalizeLocale($requestedLocale);

        try {
            $checksum = I18nCatalog::checksumFor($resolvedLocale) ?? sha1($resolvedLocale);
        } catch (\Throwable $e) {
            return $this->catalogUnavailable($e);
        }

        $etag = sprintf('W/"%s"', $checksum);

        if ($request->header('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=86400');
        }

        try {
            $catalog = I18nCatalog::readLocale($resolvedLocale);
        } catch (\Throwable $e) {
            return $this->catalogUnavailable($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'requested_locale' => $requestedLocale,
                'locale' => $resolvedLocale,
                'version' => $catalog['_version'] ?? '1.0.0',
                'updated_at' => $catalog['_updated_at'] ?? null,
                'checksum' => $checksum,
                'rtl' => I18nCatalog::isRtl($resolvedLocale),
                'fallback_locale' => 'fr',
                'catalog' => $catalog,
            ],
        ])->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=86400');
    }

    /**
     * QA 2026-08-15 (#2654) : le catalogue i18n ne doit jamais rendre une
     * page HTML 500 (constat prod : `shared/i18n` absent de l'image →
     * RuntimeException). Réponse conforme au contrat d'erreur de l'API.
     */
    private function catalogUnavailable(\Throwable $e): JsonResponse
    {
        report($e);

        return response()->json([
            'error' => 'I18N_CATALOG_UNAVAILABLE',
            'message' => 'I18N_CATALOG_UNAVAILABLE',
            'localized_message' => __('errors.SERVER_ERROR'),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
