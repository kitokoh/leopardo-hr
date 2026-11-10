<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

/**
 * #9353 (audit sécurité 2026-11-10, F11) — garde de démarrage sur les
 * credentials d'infrastructure.
 *
 * `.env.example` documentait `DB_PASSWORD=leopardo-dev-local-only`. C'est une
 * valeur de développement, pas un secret — mais un `.env.example` copié tel
 * quel en production crée un compte base de données à mot de passe connu.
 * La valeur a été vidée ; ce garde empêche qu'une valeur vide ou une valeur
 * de développement connue passe en production, même si quelqu'un la réintroduit
 * dans un fichier `.env` réel.
 *
 * Utilisé par `AppServiceProvider::boot()` : l'échec est IMMÉDIAT et bruyant
 * (le conteneur ne démarre pas), ce qui vaut mieux qu'une production qui tourne
 * avec un mot de passe public.
 */
final class InsecureConfigurationGuard
{
    /**
     * Mots de passe d'exemple connus (documentation, docker-compose, README).
     *
     * @var list<string>
     */
    private const KNOWN_DEV_PASSWORDS = [
        'leopardo-dev-local-only',
        'password',
        'secret',
        'changeme',
        'change-me',
        'postgres',
        'root',
    ];

    /**
     * Vérifie la configuration sensible pour l'environnement courant.
     *
     * @throws RuntimeException si la configuration est inacceptable
     */
    public static function check(string $environment, ?string $dbPassword): void
    {
        // Les environnements de développement et de test utilisent par
        // construction le mot de passe de docker-compose : rien à vérifier.
        if (in_array($environment, ['local', 'testing'], true)) {
            return;
        }

        $password = $dbPassword ?? '';

        if (trim($password) === '') {
            throw new RuntimeException(
                'DB_PASSWORD est vide : renseignez un secret réel en production '
                .'(#9353, audit sécurité F11).'
            );
        }

        if (in_array(strtolower(trim($password)), self::KNOWN_DEV_PASSWORDS, true)) {
            throw new RuntimeException(
                'DB_PASSWORD vaut une valeur de développement connue : '
                .'renseignez un secret réel en production (#9353, audit sécurité F11).'
            );
        }
    }
}
