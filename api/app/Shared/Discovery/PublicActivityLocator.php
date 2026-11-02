<?php

declare(strict_types=1);

namespace App\Shared\Discovery;

use App\Shared\Contracts\Discovery\PublicActivityProvider;

/**
 * COULOIR 16 — M3 (#9105) · Résolution agrégée d'une activité publique.
 *
 * Même inversion de dépendance que {@see PublicProfileLocator} : les providers
 * sont tagués `public.activity_provider` par leur verticale, et ce service ne
 * référence aucune classe `App\Modules\*` (garde d'isolation #5584).
 *
 * ## Ce que ce service garantit
 *
 *  - **Un module sans contrat n'apparaît pas.** `supports()` répond `false`
 *    pour toute verticale qui n'a pas tagué de provider, ou qui ne déclare pas
 *    le type d'activité demandé. L'onglet Activités s'appuie là-dessus : il
 *    n'affiche jamais une section qu'aucun contrat ne remplit.
 *  - **Fail-closed.** Une verticale inconnue renvoie `null`, jamais une
 *    exception distincte : un type mal orthographié côté client ne doit pas
 *    produire un message différent de « activité introuvable ».
 */
final class PublicActivityLocator
{
    /**
     * @param  iterable<PublicActivityProvider>  $providers
     */
    public function __construct(private readonly iterable $providers) {}

    /**
     * Verticales qui publient au moins un type d'activité.
     *
     * @return list<string>
     */
    public function verticals(): array
    {
        $verticals = [];

        foreach ($this->providers as $provider) {
            if ($provider->activityTypes() !== []) {
                $verticals[$provider->vertical()] = true;
            }
        }

        $verticals = array_keys($verticals);
        sort($verticals);

        return $verticals;
    }

    /**
     * Vrai si une verticale sert réellement ce type d'activité.
     *
     * C'est le test « un module sans contrat n'apparaît pas » : sans provider
     * tagué, ou sans le type déclaré, la réponse est `false` et l'écran
     * n'affiche pas la section.
     */
    public function supports(string $vertical, string $activityType): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->vertical() === $vertical
                && in_array($activityType, $provider->activityTypes(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Relit une activité dans le contexte de sa verticale, fail-closed.
     *
     * @return array{reference: string, status: string, occurred_at: string, title: string}|null
     */
    public function find(string $vertical, string $activityType, string $externalRef, int $userId): ?array
    {
        foreach ($this->providers as $provider) {
            if ($provider->vertical() !== $vertical) {
                continue;
            }

            if (! in_array($activityType, $provider->activityTypes(), true)) {
                return null;
            }

            return $provider->findForClient($activityType, $externalRef, $userId);
        }

        return null;
    }
}
