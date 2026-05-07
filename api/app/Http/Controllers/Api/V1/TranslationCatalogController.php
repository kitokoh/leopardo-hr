<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\I18nCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslationCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => I18nCatalog::readVersions(),
        ]);
    }

    public function show(Request $request, ?string $locale = null): JsonResponse|Response
    {
        $requestedLocale = $locale ?? $request->query('locale') ?? $request->header('Accept-Language');
        $resolvedLocale = I18nCatalog::normalizeLocale($requestedLocale);
        $checksum = I18nCatalog::checksumFor($resolvedLocale) ?? sha1($resolvedLocale);
        $etag = sprintf('W/"%s"', $checksum);

        if ($request->header('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=86400');
        }

        $catalog = I18nCatalog::readLocale($resolvedLocale);

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
}
