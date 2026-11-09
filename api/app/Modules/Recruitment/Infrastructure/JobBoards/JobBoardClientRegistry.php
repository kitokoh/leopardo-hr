<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use App\Modules\Recruitment\Infrastructure\Services\JobSourceLifecycle;
use InvalidArgumentException;

/**
 * BC-10 RECRUITMENT — résolution du client d'agrégateur par fournisseur.
 *
 * Un seul endroit décide quel client sert quel fournisseur. Les fournisseurs
 * dont l'API n'est pas encore câblée reçoivent un client qui lève une
 * `JobBoardUnavailableException` NON rétentive : l'écran super admin affiche
 * alors un message honnête (« pas encore câblé ») au lieu d'un faux succès ou
 * d'offres inventées.
 *
 * BC-10 (#9218) — les cinq fournisseurs commerciaux sont désormais câblés
 * (Adzuna, Jooble, Indeed, LinkedIn, Glassdoor). Le repli `NotWiredJobBoardClient`
 * est CONSERVÉ : il reste le comportement d'un fournisseur ajouté à la liste
 * fermée `JobSourceLifecycle::PROVIDERS` avant que son client n'existe. C'est
 * ce qui garantit qu'aucune offre inventée ne se retrouve sur le portail public
 * — un fournisseur sans client ne produit rien, et le dit.
 *
 * Non `final` à dessein : les tests substituent un registre qui sert un client
 * factice, ce qui permet d'exercer la synchronisation (idempotence, clôture des
 * offres disparues, gestion d'erreur) sans dépendre d'un fournisseur réel.
 */
class JobBoardClientRegistry
{
    public function __construct(
        private readonly CustomFeedJobBoardClient $customFeed,
        private readonly AdzunaJobBoardClient $adzuna,
        private readonly JoobleJobBoardClient $jooble,
        private readonly IndeedJobBoardClient $indeed,
        private readonly LinkedInJobBoardClient $linkedin,
        private readonly GlassdoorJobBoardClient $glassdoor,
    ) {}

    public function for(string $provider): JobBoardClient
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            throw new InvalidArgumentException("Fournisseur non supporté : {$provider}.");
        }

        return match ($provider) {
            // Flux XML personnalisé : interrogeable sans partenariat commercial.
            'custom_feed' => $this->customFeed,
            // Fournisseurs commerciaux (#9218) : authentification et mapping
            // propres à chacun, politique d'erreur commune (cf.
            // AbstractHttpJobBoardClient).
            'adzuna' => $this->adzuna,
            'jooble' => $this->jooble,
            'indeed' => $this->indeed,
            'linkedin' => $this->linkedin,
            'glassdoor' => $this->glassdoor,
            // Repli honnête : un fournisseur déclaré sans client câblé ne
            // produit AUCUNE offre et le dit explicitement.
            default => new NotWiredJobBoardClient($provider),
        };
    }
}
