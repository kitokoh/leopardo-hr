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

        // #7648 : fail-closed sur le TIER prod (DEPLOY_TIER=prod, #7647) —
        // même un APP_ENV permissif ne doit jamais autoriser un seed pilote
        // sur le tier de production. ALLOW_PILOT_SEEDING ne lève PAS ce refus.
        //
        // Audit architecture 2026-11-26 : lu par `config()` et non `env()`.
        // `docker-entrypoint.sh` exécute `config:cache` AVANT `db:seed` ;
        // après cela `env()` ne renvoie plus que ce que le SAPI expose, donc
        // une garde en `env()` pouvait être silencieusement inopérante.
        if (config('deployment.tier') === 'prod') {
            throw new \RuntimeException(
                "Seed pilote '{$feature}' interdit sur le tier de production (DEPLOY_TIER=prod, #7648) — jamais de données pilote en production."
            );
        }

        if (in_array($environment, $allowedEnvironments, true)) {
            return;
        }

        if ((bool) config('deployment.pilot.allow_seeding', false)) {
            return;
        }

        throw new \RuntimeException(
            "Seed pilote '{$feature}' interdit sur l'environnement '{$environment}' — jamais de données pilote en production. "
            .'Pour un environnement contrôlé, poser ALLOW_PILOT_SEEDING=true explicitement.'
        );
    }
}
