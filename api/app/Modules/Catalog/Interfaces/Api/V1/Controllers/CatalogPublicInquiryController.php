<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Events\CatalogInquiryReceived;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\Actions\SubmitCatalogInquiryAction;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\StoreCatalogInquiryRequest;
use Illuminate\Http\JsonResponse;

/**
 * Formulaire public « Demander un devis » du catalogue B2B (BC-28 CATALOG,
 * C-LEAD #6884).
 *
 * `POST /v1/public/catalog/{companySlug}/inquiries` — SANS auth (route
 * isolée, `throttle:shop-public` + `catalog.public` ; le tenant est résolu
 * par slug, 404 fail-closed, contexte tenant déjà posé par le middleware).
 *
 * Flux spec §7 : honeypot (201 factice, rien n'est persisté) → validation
 * stricte (produit publié exigé) → stockage `catalog_inquiries`
 * (minimisation RGPD, consentement horodaté, conservation bornée) →
 * événement `CatalogInquiryReceived` (contrat cross-BC : lead CRM BC-11 +
 * notification tenant BC-13) → accusé de réception acheteur (sans
 * engagement). Aucune donnée acheteur n'est jamais exposée publiquement.
 */
class CatalogPublicInquiryController extends Controller
{
    public function store(StoreCatalogInquiryRequest $request): JsonResponse
    {
        // Honeypot anti-spam : un bot qui remplit le champ caché reçoit un
        // accusé factice — aucun lead n'est persisté, le bot ne sait pas
        // qu'il est détecté.
        if ($request->filled('company_website')) {
            return new JsonResponse(['data' => ['status' => 'received']], 201);
        }

        $company = currentCompany();

        // Re-vérification fail-closed produit publié, persistance minimisée
        // RGPD et émission de `CatalogInquiryReceived` : use case porté par
        // SubmitCatalogInquiryAction. Retour `null` = produit dépublié entre
        // la validation et l'écriture (contrat 422 inchangé).
        $inquiry = app(SubmitCatalogInquiryAction::class)->execute(
            (string) $company->id,
            $request->validated(),
            $request->ip(),
        );

        if (! $inquiry instanceof CatalogInquiry) {
            return new JsonResponse(['error' => 'PRODUCT_NOT_PUBLISHED'], 422);
        }

        return new JsonResponse([
            'data' => [
                'status' => 'received',
                'reference' => (int) $inquiry->id,
            ],
        ], 201);
    }
}
