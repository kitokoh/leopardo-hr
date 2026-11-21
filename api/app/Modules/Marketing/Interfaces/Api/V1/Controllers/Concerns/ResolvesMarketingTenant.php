<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers\Concerns;

use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;

/**
 * Helpers partagés par les contrôleurs Marketing OS (surface SUPER ADMIN).
 *
 * ## Pourquoi un trait plutôt qu'une classe de base
 *
 * `PlatformMarketingOsController` portait 1 022 lignes et quatre ressources
 * distinctes (campagnes/idées, médiathèque, publications, visibilité). Le
 * découpage par ressource est la règle du dépôt (un contrôleur = un agrégat
 * HTTP), mais les quatre nouveaux contrôleurs partagent trois garanties qui
 * ne doivent exister qu'à UN endroit :
 *
 *  - la pagination bornée (`DEFAULT_PER_PAGE` / `MAX_PER_PAGE`) ;
 *  - l'échappement du motif `LIKE`/`ILIKE` (un `%` saisi ne doit pas être un
 *    joker) ;
 *  - les gardes de rattachement tenant (`assert*BelongsToTenant`), qui
 *    empêchent de lier une idée ou une ressource à la campagne d'un AUTRE
 *    tenant.
 *
 * Un trait (et non une classe abstraite) parce que ces contrôleurs n'ont pas
 * d'état commun : ils n'héritent que de `Controller`, et une hiérarchie
 * supplémentaire n'apporterait rien.
 *
 * ## Isolation tenant
 *
 * Ces gardes s'exécutent DANS le contexte du tenant ciblé
 * (`TenantManager::withinTenant()`), donc le scope `BelongsToCompany` du
 * modèle ajoute déjà le filtre `company_id` : une entité d'une autre
 * entreprise est introuvable, et la garde répond 422 plutôt que de laisser
 * une référence orpheline.
 */
trait ResolvesMarketingTenant
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * Motif `LIKE`/`ILIKE` sûr, pour une recherche utilisateur.
     *
     * Sans échappement, un `%` ou un `_` saisi dans le champ de recherche est
     * interprété comme un JOKER : chercher « 100% » ramenait tout le
     * catalogue, et « a_b » ramenait « axb ». Le dépôt applique déjà cette
     * règle partout ailleurs (`addcslashes($v, '%_\\')`) : la médiathèque ne
     * peut pas être la seule surface où la recherche se comporte autrement.
     */
    private function likePattern(string $value): string
    {
        return '%'.addcslashes(trim($value), '%_\\').'%';
    }

    /** Empêche de rattacher une idée/ressource à une campagne d'un autre tenant. */
    private function assertCampaignBelongsToTenant(?int $campaignId): void
    {
        if ($campaignId === null) {
            return;
        }

        $exists = MarketingCampaign::query()->whereKey($campaignId)->exists();
        abort_unless($exists, 422, __('marketing.os.campaign_not_found'));
    }

    private function assertIdeaBelongsToTenant(?int $ideaId): void
    {
        if ($ideaId === null) {
            return;
        }

        abort_unless(ContentIdea::query()->whereKey($ideaId)->exists(), 422, __('marketing.os.idea_not_found'));
    }

    private function assertAssetBelongsToTenant(?int $assetId): void
    {
        if ($assetId === null) {
            return;
        }

        abort_unless(MarketingAsset::query()->whereKey($assetId)->exists(), 422, __('marketing.os.asset_not_found'));
    }
}
