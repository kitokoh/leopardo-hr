<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * #6754 — registre partagé de l'état de rafraîchissement tenant entre les
 * classes de test d'un même process PHPUnit.
 *
 * Une propriété statique déclarée dans un trait est DUPLIQUÉE par classe
 * utilisatrice en PHP : elle ne peut donc pas servir de mémoire
 * inter-fichiers. Ce petit holder centralise l'identité de la dernière
 * classe de test ayant migré, pour que `RefreshTenantDatabase` force une
 * re-migration propre à la frontière ENTRE fichiers (jamais entre deux
 * tests du même fichier).
 */
final class TenantRefreshRegistry
{
    /** @var class-string|null */
    public static ?string $lastRefreshClass = null;
}
