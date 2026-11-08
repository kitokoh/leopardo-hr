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
 */
final class JobBoardClientRegistry
{
    public function __construct(
        private readonly CustomFeedJobBoardClient $customFeed,
    ) {}

    public function for(string $provider): JobBoardClient
    {
        if (! JobSourceLifecycle::isSupportedProvider($provider)) {
            throw new InvalidArgumentException("Fournisseur non supporté : {$provider}.");
        }

        return match ($provider) {
            // Seul le flux XML personnalisé est réellement interrogeable
            // aujourd'hui : il ne dépend d'aucun partenariat commercial.
            'custom_feed' => $this->customFeed,
            default => new NotWiredJobBoardClient($provider),
        };
    }
}
