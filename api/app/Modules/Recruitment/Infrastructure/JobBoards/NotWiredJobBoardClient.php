<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 RECRUITMENT — client d'un fournisseur dont l'API n'est pas câblée.
 *
 * Il ne simule RIEN : il lève une exception non rétentive, que le service de
 * synchronisation traduit en `error` motivé sur le connecteur. C'est la
 * garantie qu'aucune offre inventée ne se retrouve sur le portail public.
 */
final class NotWiredJobBoardClient implements JobBoardClient
{
    public function __construct(private readonly string $provider) {}

    public function fetch(array $credentials, array $config): array
    {
        throw JobBoardUnavailableException::notWired($this->provider);
    }
}
