<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Définition d'un type de bloc fourni par un BC vertical (BC PAGES, #8724).
 *
 * Valeur immuable : une fois construits, les types sont un **catalogue figé**
 * pour la durée de la requête. Un schéma mutable pendant la validation est un
 * bug qui ne se voit qu'en production, sous charge, quand deux requêtes se
 * chevauchent — et il produit un contenu validé contre un schéma, rendu selon
 * un autre.
 *
 * `schema` est un document JSON Schema (draft-07, sous-ensemble supporté par
 * {@see \App\Modules\Pages\Domain\Blocks\PageBlockSchemaValidator}). Les
 * contraintes exigées par l'issue sont portées par le schéma lui-même :
 * `additionalProperties: false` par défaut, longueurs maximales, cardinalités
 * bornées, et un `format` d'URL restreint pour tout champ de lien.
 */
final readonly class PageBlockTypeDefinition
{
    /**
     * @param  string  $id  Identifiant qualifié : `<bc>.<nom>` en minuscules, `[a-z0-9_]` séparé par `.`
     * @param  string  $ownerBc  BC propriétaire (doit préfixer `$id`)
     * @param  int  $schemaVersion  Version du contrat de ce type (>= 1)
     * @param  array<string, mixed>  $schema  JSON Schema (sous-ensemble supporté)
     * @param  string|null  $renderer  Nom du renderer enregistré ; `null` = type connu mais non rendable
     * @param  bool  $isCore  Type du socle (toujours disponible) vs fourni par un vertical
     * @param  string  $label  Libellé court pour l'éditeur (i18n côté client, non traduit ici)
     */
    public function __construct(
        public string $id,
        public string $ownerBc,
        public int $schemaVersion,
        public array $schema,
        public ?string $renderer = null,
        public bool $isCore = false,
        public string $label = '',
    ) {
        if ($schemaVersion < 1) {
            throw new \InvalidArgumentException(
                sprintf('Le type de bloc « %s » doit déclarer un schemaVersion >= 1 (reçu %d).', $id, $schemaVersion)
            );
        }

        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $id)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Identifiant de bloc non qualifié : « %s ». Le format attendu est `<bc>.<nom>` en minuscules (ex. `core.hero`).',
                    $id,
                ),
            );
        }

        $expectedPrefix = $ownerBc.'.';
        if (! str_starts_with($id, $expectedPrefix)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Le type « %s » déborde de son espace de noms : il doit commencer par « %s ».',
                    $id,
                    $expectedPrefix,
                ),
            );
        }

        // Un schéma sans `type` explicite accepterait n'importe quoi (y compris
        // une chaîne contenant du HTML) : on exige un objet à la racine, c'est
        // la seule forme que le validateur et les renderers savent traiter.
        if (($schema['type'] ?? null) !== 'object') {
            throw new \InvalidArgumentException(
                sprintf('Le schéma du bloc « %s » doit avoir « type »: "object" à la racine.', $id)
            );
        }
    }

    /**
     * Le bloc de ce type peut-il être rendu sur une surface publique ?
     *
     * `false` quand aucun renderer n'est enregistré : le contenu reste stocké
     * (rien n'est perdu) mais il est **omis du rendu avec télémétrie** plutôt
     * que d'être exécuté comme markup brut — c'est la règle de sécurité de
     * l'issue.
     */
    public function isRenderable(): bool
    {
        return $this->renderer !== null && $this->renderer !== '';
    }
}
