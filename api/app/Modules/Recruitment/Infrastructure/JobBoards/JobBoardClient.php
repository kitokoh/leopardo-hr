<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

/**
 * BC-10 RECRUITMENT — contrat d'un client d'agrégateur d'offres.
 *
 * Un client par fournisseur : il connaît l'authentification, le format de
 * réponse et le mapping vers `JobBoardOffer`. Le service de synchronisation ne
 * connaît que cette interface.
 */
interface JobBoardClient
{
    /**
     * Récupère les offres du fournisseur.
     *
     * @param  array<string, string>  $credentials  identifiants déchiffrés
     * @param  array<string, mixed>  $config  filtres d'import (pays, catégories, limite)
     * @return list<JobBoardOffer>
     *
     * @throws JobBoardUnavailableException  le fournisseur n'est pas joignable
     *                                       ou n'est pas encore câblé
     */
    public function fetch(array $credentials, array $config): array;
}
