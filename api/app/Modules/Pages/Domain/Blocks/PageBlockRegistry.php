<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

use App\Modules\Pages\Domain\Exceptions\DuplicatePageBlockTypeException;

/**
 * Registre des types de blocs de Pages (BC PAGES, #8724).
 *
 * Rassemble le socle (`core.*`, fourni par Pages) et tout ce que les BC
 * verticaux enregistrent via le tag de conteneur `pages.block_providers`.
 *
 * ## Invariants
 *
 * 1. **Un identifiant = un fournisseur.** Deux BC qui déclarent `core.hero`
 *    sont une erreur de configuration qui doit être vue au boot, pas un
 *    `array_merge` silencieux où le dernier gagne. On lève donc à
 *    l'enregistrement.
 * 2. **Un fournisseur ne sort pas de son espace de noms** (vérifié par
 *    {@see PageBlockTypeDefinition}).
 * 3. **Le registre est figé après construction.** `all()` renvoie une copie ;
 *    aucun appelant ne peut injecter un type à la volée depuis une requête.
 *    Un `register()` exposé publiquement serait une porte d'entrée pour
 *    exécuter du HTML arbitraire — exactement ce que l'issue interdit.
 *
 * Le registre ne fait **aucune E/S** : il est construit une fois par le
 * service provider du module, à partir de la configuration/m du conteneur.
 * C'est ce qui le rend testable sans base ni HTTP.
 */
final class PageBlockRegistry
{
    /** @var array<string, PageBlockTypeDefinition> */
    private array $types = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  iterable<PageBlockProvider>  $providers
     */
    public static function build(iterable $providers, bool $includeCore = true): self
    {
        $registry = new self;

        if ($includeCore) {
            foreach (CorePageBlocks::types() as $definition) {
                $registry->register($definition);
            }
        }

        foreach ($providers as $provider) {
            foreach ($provider->blockTypes() as $definition) {
                $registry->register($definition);
            }
        }

        return $registry;
    }

    /**
     * Enregistre un type.
     *
     * Une collision est une erreur de programmation : on lève, on ne remplace
     * pas. Le message nomme les deux propriétaires pour rendre le diagnostic
     * direct (sans quoi on cherche pendant une heure quel module a écrasé
     * `restaurant.menu`).
     *
     * @throws DuplicatePageBlockTypeException
     */
    public function register(PageBlockTypeDefinition $definition): void
    {
        $existing = $this->types[$definition->id] ?? null;

        if ($existing instanceof PageBlockTypeDefinition) {
            throw DuplicatePageBlockTypeException::between($definition->id, $existing->ownerBc, $definition->ownerBc);
        }

        $this->types[$definition->id] = $definition;
    }

    public function has(string $id): bool
    {
        return isset($this->types[$id]);
    }

    public function get(string $id): ?PageBlockTypeDefinition
    {
        return $this->types[$id] ?? null;
    }

    /**
     * @return array<string, PageBlockTypeDefinition>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Types fournis par un BC donné.
     *
     * @return array<string, PageBlockTypeDefinition>
     */
    public function byOwner(string $ownerBc): array
    {
        return array_filter(
            $this->types,
            static fn (PageBlockTypeDefinition $d): bool => $d->ownerBc === $ownerBc,
        );
    }

    /**
     * Types qui n'ont pas de renderer : stockés, jamais rendus.
     *
     * Exposé pour la télémétrie (#8730) : un type non rendable qui apparaît
     * dans des Pages publiées est un signal produit (le vertical a livré son
     * contrat mais pas son rendu).
     *
     * @return list<string>
     */
    public function unrenderableIds(): array
    {
        return array_values(array_map(
            static fn (PageBlockTypeDefinition $d): string => $d->id,
            array_filter($this->types, static fn (PageBlockTypeDefinition $d): bool => ! $d->isRenderable()),
        ));
    }

    /**
     * Avertissements non bloquants accumulés pendant la construction.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }
}
