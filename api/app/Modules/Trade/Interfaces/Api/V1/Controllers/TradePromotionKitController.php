<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Domain\Models\TradeProduct;
use App\Modules\Trade\Infrastructure\Services\TradePromotionKitBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kit de promotion de l'entreprise — BC-36 TRADE, #9783.
 *
 * `GET /api/v1/trade/promotion-kit`
 *
 * ## Pourquoi une route dédiée plutôt que des champs de plus dans le profil
 *
 * Le profil (`PUT /trade/profile`) sert à DÉCRIRE son activité. Ce kit sert à
 * la PROMOUVOIR : il répond à « qu'est-ce qui me manque pour être crédible dans
 * l'annuaire, et quel texte puis-je publier ? ». C'est un acte de lecture, il
 * ne modifie rien — un `GET` qui écrit serait un piège.
 *
 * ## Réservé à l'admin de la verticale
 *
 * Le kit expose la synthèse commerciale du tenant (directions, marchés,
 * catalogue) et prépare un acte de publication. Comme `PUT /trade/profile`, il
 * est réservé au rôle `admin` de la verticale Trade : un membre en lecture
 * seule n'a pas à préparer la communication publique de l'entreprise.
 */
final class TradePromotionKitController extends Controller
{
    public function __construct(
        private readonly TradePromotionKitBuilder $builder,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var TradeCompanyProfile|null $profile */
        $profile = TradeCompanyProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        // Le catalogue est lu par le scope tenant du modèle : on ne peut pas
        // citer le produit d'une autre entreprise dans un brouillon de
        // présentation.
        /** @var list<TradeProduct> $products */
        $products = TradeProduct::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->all();

        return new JsonResponse([
            'data' => $this->builder->build($profile, $products),
        ]);
    }
}
