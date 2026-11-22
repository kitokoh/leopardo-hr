<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Trade\Application\Actions\SubmitPublicTradeInquiryAction;
use App\Modules\Trade\Interfaces\Api\V1\Requests\PublicTradeInquiryRequest;
use App\Shared\Contracts\Discovery\PublicProfileLocator;
use Illuminate\Http\JsonResponse;

/**
 * Demande de devis d'un visiteur PUBLIC vers un vendeur B2B — #9779.
 *
 * ## Le trou que cette route comble
 *
 * La fiche publique et son catalogue rendaient le vendeur VISIBLE, mais un
 * acheteur qui trouvait le bon fournisseur n'avait aucun moyen de le joindre.
 * Le catalogue etait une vitrine sans porte : la visite ne produisait jamais
 * de contact, donc le vendeur ne pouvait pas etre promu au sens de la mission.
 *
 * ## Perimetre : meme resolution fail-closed que la fiche
 *
 * `PublicProfileLocator` + `TradeProfileResolver` appliquent deja `is_public`,
 * slug unique global, `public_name` non vide, societe non suspendue/expiree et
 * feature `trade` active. Un profil non publiable produit le **meme 404
 * uniforme** que la fiche : impossible de distinguer « slug inexistant » de
 * « depublie », donc impossible d'enumerer les professionnels inscrits.
 *
 * ## Reponse uniforme
 *
 * `202 {"status":"received"}` dans TOUS les cas de succes, sans identifiant de
 * lead ni nom du tenant. Un visiteur ne doit pas pouvoir sonder l'existence
 * d'un tenant par ce canal, ni compter les leads deposes.
 */
class TradePublicInquiryController extends Controller
{
    public function __construct(
        private readonly PublicProfileLocator $locator,
        private readonly SubmitPublicTradeInquiryAction $submit,
    ) {}

    public function store(PublicTradeInquiryRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->locator->locate('trade_company', $slug);

        if ($tenant === null) {
            abort(404);
        }

        $companyId = $tenant['company_id'];

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            abort(404);
        }

        $this->submit->execute($company, $request->validated());

        return response()->json(['status' => 'received'], 202);
    }
}
