<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatsSource;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Infrastructure\Services\Stats\PlatformStatsProviderResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marketing OS — remontee des statistiques REELLES d'une publication (#8530).
 *
 * Regle centrale : ce service ne fabrique JAMAIS de chiffre. Trois issues
 * seulement sont possibles, et chacune est explicite en base :
 *
 *   1. l'API de la plateforme renvoie des indicateurs
 *      → `stats` renseigne, `stats_source = platform` ;
 *   2. la plateforme ne fournit pas la donnee (pas d'API, pas de connecteur,
 *      pas d'identifiant externe, reponse vide)
 *      → `stats_source = unavailable` + `stats_error` expliquant pourquoi,
 *        et `stats` laisse INTACT (on ne detruit pas une mesure anterieure,
 *        on ne la remplace surtout pas par une estimation) ;
 *   3. l'appel API echoue techniquement
 *      → meme etat `unavailable`, avec le message d'erreur conserve.
 *
 * `stats_fetched_at` est renseigne dans TOUS les cas : l'interface peut donc
 * distinguer « jamais interroge » de « interroge, rien recu ».
 */
class MarketingPublicationStatsService
{
    public function __construct(
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly PlatformStatsProviderResolver $providers,
    ) {}

    public function refresh(MarketingPublication $publication): MarketingPublication
    {
        $publication->stats_fetched_at = Carbon::now();

        $account = $this->socialAccounts->findForCompany($publication->company_id);

        if (! $account instanceof SocialAccount || ! $account->isActive()) {
            return $this->markUnavailable(
                $publication,
                'Aucun compte social actif connecte : les statistiques ne peuvent pas etre recuperees.',
            );
        }

        $externalId = $publication->external_post_id;

        if (! is_string($externalId) || $externalId === '') {
            return $this->markUnavailable(
                $publication,
                'Aucun identifiant de publication externe : la publication n a pas ete executee par une API.',
            );
        }

        $provider = $this->providers->resolve($publication->platform);

        if ($provider === null) {
            return $this->markUnavailable(
                $publication,
                "La plateforme '{$publication->platform}' ne fournit pas de statistiques via l API.",
            );
        }

        try {
            $stats = $provider->fetch($account->provider_profile_ref, $externalId, $publication->platform);
        } catch (Throwable $e) {
            Log::warning('Marketing: platform stats fetch failed', [
                'marketing_publication_id' => $publication->id,
                'platform' => $publication->platform,
                'error' => $e->getMessage(),
            ]);

            return $this->markUnavailable($publication, $e->getMessage());
        }

        if ($stats === null || $stats === []) {
            return $this->markUnavailable(
                $publication,
                'La plateforme n a renvoye aucune statistique pour cette publication.',
            );
        }

        $publication->stats = $stats;
        $publication->stats_source = MarketingPublicationStatsSource::Platform->value;
        $publication->stats_error = null;
        $publication->save();

        return $publication;
    }

    /**
     * Declare la donnee indisponible SANS toucher a `stats` : une mesure
     * anterieure reste consultable, et aucune valeur n'est inventee.
     */
    private function markUnavailable(MarketingPublication $publication, string $reason): MarketingPublication
    {
        $publication->stats_source = MarketingPublicationStatsSource::Unavailable->value;
        $publication->stats_error = $reason;
        $publication->save();

        return $publication;
    }
}
