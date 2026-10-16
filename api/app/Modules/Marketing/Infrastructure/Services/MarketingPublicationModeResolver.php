<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatsSource;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — décision « publication directe ou assistée » (brief §F).
 *
 * Règle métier centrale du module : on n'annonce JAMAIS une automatisation
 * qui n'existe pas.
 *
 *   - un agrégateur est configuré ET actif pour le tenant
 *     (`social_accounts.status = active`) → le mode `direct` est possible,
 *     la publication peut être exécutée par l'API ;
 *   - sinon → le mode est `assisted` : Leopardo prépare le texte, les
 *     hashtags et le média, ouvre la page officielle, et l'utilisateur
 *     confirme lui-même la publication. Le passage au statut `published`
 *     exige alors une confirmation EXPLICITE et l'URL réelle.
 *
 * Aucun chemin de code ne marque un contenu `published` parce qu'une page
 * de réseau social a simplement été ouverte.
 */
class MarketingPublicationModeResolver
{
    public function __construct(private readonly SocialAccountRepositoryInterface $socialAccounts) {}

    /** Le tenant dispose-t-il d'un canal de publication directe opérationnel ? */
    public function isDirectAvailable(string $companyId): bool
    {
        $account = $this->socialAccounts->findForCompany($companyId);

        return $account instanceof SocialAccount && $account->isActive();
    }

    /** Mode effectif pour ce tenant, au moment de la demande. */
    public function resolve(string $companyId): MarketingPublicationMode
    {
        return $this->isDirectAvailable($companyId)
            ? MarketingPublicationMode::Direct
            : MarketingPublicationMode::Assisted;
    }

    /**
     * Marque une publication comme réellement mise en ligne.
     *
     * @param  array<string, mixed>|null  $stats
     */
    public function markPublished(
        MarketingPublication $publication,
        string $publishedUrl,
        ?string $platformAccount = null,
        ?Carbon $publishedAt = null,
        ?array $stats = null,
        ?string $externalPostId = null,
    ): MarketingPublication {
        $publication->status = MarketingPublicationStatus::Published;
        $publication->published_url = $publishedUrl;
        $publication->published_at = $publishedAt ?? Carbon::now();
        $publication->platform_account = $platformAccount ?? $publication->platform_account;
        $publication->error_message = null;

        // #8530 — identifiant externe : sans lui, l'API de statistiques de la
        // plateforme ne peut pas etre interrogee. On ne l'ecrase jamais par
        // une valeur nulle (une publication deja tracee le reste).
        if ($externalPostId !== null && $externalPostId !== '') {
            $publication->external_post_id = $externalPostId;
        }

        if ($stats !== null) {
            $publication->stats = $stats;
            // Releve saisi par un humain : la provenance est `manual`, jamais
            // `platform` (reserve aux valeurs renvoyees par une API).
            $publication->stats_source = MarketingPublicationStatsSource::Manual->value;
            $publication->stats_error = null;
        }

        $publication->attempt_history = $this->appendAttempt($publication, 'published');
        $publication->save();

        return $publication;
    }

    /** Marque un échec en conservant l'historique des tentatives. */
    public function markFailed(MarketingPublication $publication, string $errorMessage): MarketingPublication
    {
        $publication->status = MarketingPublicationStatus::Failed;
        $publication->error_message = $errorMessage;
        $publication->attempts++;
        $publication->attempt_history = $this->appendAttempt($publication, 'failed', $errorMessage);
        $publication->save();

        return $publication;
    }

    /**
     * L'historique des tentatives n'est JAMAIS écrasé : on ajoute une entrée.
     *
     * @return array<int, array<string, string>>
     */
    private function appendAttempt(MarketingPublication $publication, string $outcome, ?string $error = null): array
    {
        /** @var array<int, array<string, string>> $history */
        $history = is_array($publication->attempt_history) ? $publication->attempt_history : [];

        $entry = [
            'at' => Carbon::now()->toIso8601String(),
            'outcome' => $outcome,
            'mode' => $publication->mode->value,
        ];

        if ($error !== null) {
            $entry['error'] = $error;
        }

        $history[] = $entry;

        return $history;
    }
}
