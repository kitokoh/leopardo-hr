<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Infrastructure\Services\TradeIntegrationRegistry;
use Illuminate\Http\JsonResponse;

/**
 * TRADE-007 (#8482, BC-36 TRADE, spec §8) — metadonnees de la verticale.
 *
 * `GET /trade/meta/integrations` expose l'etat REEL des adaptateurs externes
 * (`{key, configured, available, mode, reason}`) au front tenant : c'est ce
 * qui permet aux ecrans d'afficher « non configure » au lieu de presenter une
 * fonctionnalite indisponible comme operationnelle.
 *
 * Lecture pure, derriere la gate `module.trade` et l'authentification tenant ;
 * aucun secret ne transite (cf. TradeIntegrationRegistry).
 *
 * @see \App\Modules\Trade\Infrastructure\Services\TradeIntegrationRegistry
 */
final class TradeMetaController
{
    public function __construct(
        private readonly TradeIntegrationRegistry $integrations,
    ) {}

    public function integrations(): JsonResponse
    {
        $all = $this->integrations->all();

        return new JsonResponse([
            'data' => [
                'integrations' => $all,
                'degraded' => ! $this->integrations->allConfigured(),
                // Formulation produit : l'utilisateur doit comprendre que les
                // fonctions dependant d'un adaptateur absent ne sont pas
                // seulement « vides », elles sont INDISPONIBLES.
                'notice' => 'Les fonctions dependant d\'un adaptateur non configure restent indisponibles et sont signalees comme telles. Aucune donnee n\'est simulee pour les combler.',
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
