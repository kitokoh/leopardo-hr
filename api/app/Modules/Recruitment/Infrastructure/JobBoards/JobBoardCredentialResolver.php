<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use App\Modules\Recruitment\Infrastructure\Services\JobSourceLifecycle;

/**
 * BC-10 (#9218) — résolution des identifiants d'un fournisseur.
 *
 * Deux sources, dans cet ordre :
 *
 * 1. **La base** (saisie par le super admin, chiffrée au repos). Elle PRIME :
 *    si un opérateur a explicitement configuré un connecteur, sa saisie doit
 *    gagner — sinon un déploiement changerait silencieusement les identifiants
 *    sous les pieds de l'exploitant.
 *
 * 2. **L'environnement**, en repli. C'est ce qui permet de déployer un
 *    connecteur sans passer par l'écran super admin (infrastructure as code,
 *    rotation de secret par le gestionnaire de secrets). Sans ce repli, un
 *    fournisseur ne pourrait être activé que manuellement, ce qui rend
 *    impossible tout provisionnement automatisé.
 *
 * La fusion est **par clé**, pas par jeu : un connecteur dont la base ne
 * contient que `app_id` peut recevoir `app_key` de l'environnement. C'est
 * volontaire — un secret partiel en base (par exemple parce qu'un opérateur a
 * tourné la clé) ne doit pas rendre le connecteur inutilisable.
 *
 * Les noms de variables suivent le fournisseur et la clé :
 * `RECRUITMENT_JOBBOARD_{PROVIDER}_{KEY}` — ex.
 * `RECRUITMENT_JOBBOARD_ADZUNA_APP_ID`.
 */
final class JobBoardCredentialResolver
{
    /**
     * @param  array<string, string>  $stored  identifiants déchiffrés venant de la base
     * @return array<string, string>  identifiants effectifs (base prioritaire, env en repli)
     */
    public function resolve(string $provider, array $stored): array
    {
        $resolved = [];

        foreach (JobSourceLifecycle::credentialKeysFor($provider) as $key) {
            $value = $stored[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $value = $this->fromEnvironment($provider, $key);
            }

            if (is_string($value) && trim($value) !== '') {
                $resolved[$key] = trim($value);
            }
        }

        return $resolved;
    }

    /**
     * Un fournisseur est-il configurable, d'une source ou de l'autre ?
     *
     * Sert à l'écran super admin : un connecteur alimenté uniquement par
     * l'environnement doit apparaître comme configuré, sinon l'opérateur
     * croirait devoir saisir des identifiants déjà présents.
     */
    public function isConfigured(string $provider, array $stored): bool
    {
        $required = JobSourceLifecycle::credentialKeysFor($provider);

        if ($required === []) {
            return false;
        }

        $resolved = $this->resolve($provider, $stored);

        return array_diff($required, array_keys($resolved)) === [];
    }

    private function fromEnvironment(string $provider, string $key): ?string
    {
        $name = 'RECRUITMENT_JOBBOARD_'.strtoupper($provider).'_'.strtoupper($key);
        $value = env($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
