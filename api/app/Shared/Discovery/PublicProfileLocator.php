<?php

declare(strict_types=1);

namespace App\Shared\Discovery;

use App\Shared\Contracts\Discovery\PublicProfileResolver;

/**
 * SUPERAPP-P1 (#8760) — Résolution agrégée d'un profil public par (type, slug).
 *
 * Même inversion de dépendance que {@see PublicDiscoveryService} : les
 * résolveurs sont tagués `public.profile_resolver` par leur verticale, et ce
 * service ne référence aucune classe `App\Modules\*`.
 *
 * Un `type` inconnu est REFUSÉ (pas de filtrage silencieux) : sans cela, une
 * faute de frappe côté client pourrait router une demande vers la mauvaise
 * verticale.
 */
final class PublicProfileLocator
{
    /**
     * @param  iterable<PublicProfileResolver>  $resolvers
     */
    public function __construct(private readonly iterable $resolvers) {}

    /**
     * Types canoniques résolubles (sert de valeurs autorisées à `target_type`).
     *
     * @return list<string>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->resolvers as $resolver) {
            $types[$resolver->type()] = true;
        }

        $types = array_keys($types);
        sort($types);

        return $types;
    }

    /**
     * Résout le tenant propriétaire d'un profil public, fail-closed.
     *
     * @return array{company_id: string, display_name: string}|null null si le profil n'est pas joignable
     */
    public function locate(string $type, string $slug): ?array
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->type() !== $type) {
                continue;
            }

            return $resolver->resolve($slug);
        }

        return null;
    }

    public function supports(string $type): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->type() === $type) {
                return true;
            }
        }

        return false;
    }
}
