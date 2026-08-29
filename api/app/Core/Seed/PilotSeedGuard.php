<?php

declare(strict_types=1);

namespace App\Core\Seed;

use RuntimeException;

/**
 * MAT-012 (#5870) — garde des seeds pilotes et données synthétiques.
 *
 * Les seeds pilotes sont reproductibles, idempotents et nettoyables, mais ne
 * doivent JAMAIS cibler un tenant de production par erreur :
 *
 * 1. `assertEnvironment()` : refus de l'exécution en production sans
 *    `--force` explicite ;
 * 2. `assertPilotSlug()` : seuls les slugs de l'allowlist pilote/demo
 *    peuvent être semés ou nettoyés — un slug de client réel est refusé,
 *    même avec `--force`.
 */
final class PilotSeedGuard
{
    /**
     * Slugs autorisés pour les seeds/cleanup pilotes (allowlist stricte).
     *
     * @var list<string>
     */
    public const ALLOWED_PILOT_SLUGS = [
        'crm-pilot-alpha',
        'crm-pilot-beta',
        'techcorp-algerie',
        'pharmaplus-casablanca',
        'digitalflow-tunis',
    ];

    /**
     * Refuse l'exécution en production sauf `--force` explicite.
     */
    public function assertEnvironment(string $environment, bool $force = false): void
    {
        if ($force) {
            return;
        }

        if ($environment === 'production') {
            throw new RuntimeException(
                'Les seeds pilotes sont refusés en production sans --force (risque de ciblage d\'un tenant réel).'
            );
        }
    }

    /**
     * Refuse tout slug hors allowlist pilote/demo.
     */
    public function assertPilotSlug(string $slug): void
    {
        if (! in_array($slug, self::ALLOWED_PILOT_SLUGS, true)) {
            throw new RuntimeException(
                sprintf('Le slug [%s] n\'est pas dans l\'allowlist pilote — ciblage d\'un tenant réel refusé.', $slug)
            );
        }
    }
}
