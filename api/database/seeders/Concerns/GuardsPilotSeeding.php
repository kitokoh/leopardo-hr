<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

/**
 * MAT-012 (#5870) — Garde d'environnement pour les seeds pilotes.
 *
 * Un seed pilote crée des données synthétiques dans des tenants dédiés
 * (`*-pilot-*`) : il ne doit JAMAIS pouvoir cibler un tenant de production
 * par erreur. La garde refuse tout environnement hors local/development/
 * testing/staging, sauf ALLOW_PILOT_SEEDING=true explicite.
 */
trait GuardsPilotSeeding
{
    /**
     * @param  list<string>  $allowedEnvironments
     */
    private function assertPilotEnvironmentAllowed(string $feature, array $allowedEnvironments = ['local', 'development', 'testing', 'staging']): void
    {
        $environment = (string) app()->environment();

        if (in_array($environment, $allowedEnvironments, true)) {
            return;
        }

        if ((bool) env('ALLOW_PILOT_SEEDING', false)) {
            return;
        }

        throw new \RuntimeException(
            "Seed pilote '{$feature}' interdit sur l'environnement '{$environment}' — jamais de données pilote en production. "
            .'Pour un environnement contrôlé, poser ALLOW_PILOT_SEEDING=true explicitement.'
        );
    }
}
