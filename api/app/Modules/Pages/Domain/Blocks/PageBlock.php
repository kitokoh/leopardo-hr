<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Un bloc tel que stocké dans une Page (BC PAGES, #8724).
 *
 * Forme persistée (JSONB, dans `page_blocks` — #8723/#8730) :
 *
 * ```json
 * {
 *   "id": "b_01J...",
 *   "type": "restaurant.menu",
 *   "schema_version": 1,
 *   "position": 2,
 *   "content": { "…": "…" },
 *   "locales": { "fr": { "…": "…" } }
 * }
 * ```
 *
 * ## Pourquoi `schema_version` est stocké par bloc
 *
 * Le type peut évoluer. Un bloc écrit en v1 et un bloc écrit en v2 coexistent
 * dans la même Page pendant la transition. Sans la version sur la ligne, il
 * faudrait supposer que tout est à la dernière version — et un contenu v1
 * interprété comme v2 est un contenu rendu avec les mauvais champs, donc
 * silencieusement faux. Le stockage par bloc est ce qui rend la migration
 * progressive possible.
 *
 * ## Ce que cette classe refuse de faire
 *
 * Elle ne « répare » jamais un contenu : pas de valeur par défaut inventée,
 * pas de clé inconnue retirée en silence. Un bloc invalide est **rapporté**
 * (critère d'acceptation : « les contenus invalides sont rapportés »), pas
 * corrigé — une correction silencieuse transforme une donnée cassée en donnée
 * plausible, ce qui est pire pour l'exploitant qui ne saura jamais que son
 * contenu a été modifié.
 */
final readonly class PageBlock
{
    /**
     * @param  array<string, mixed>  $content  Contenu (langue par défaut)
     * @param  array<string, array<string, mixed>>  $locales  Traductions par locale
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $schemaVersion,
        public int $position,
        public array $content,
        public array $locales = [],
    ) {}

    /**
     * Reconstruit un bloc depuis sa forme persistée.
     *
     * Tolérant à la lecture (une Page mal formée doit rester lisible pour être
     * réparée), strict à l'écriture (cf. {@see PageBlockCollection::validate()}).
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        /** @var array<string, mixed> $content */
        $content = is_array($raw['content'] ?? null) ? $raw['content'] : [];

        /** @var array<string, array<string, mixed>> $locales */
        $locales = [];
        if (is_array($raw['locales'] ?? null)) {
            foreach ($raw['locales'] as $locale => $payload) {
                if (is_string($locale) && is_array($payload)) {
                    $locales[$locale] = $payload;
                }
            }
        }

        return new self(
            id: is_string($raw['id'] ?? null) ? $raw['id'] : '',
            type: is_string($raw['type'] ?? null) ? $raw['type'] : '',
            schemaVersion: is_int($raw['schema_version'] ?? null) ? $raw['schema_version'] : 1,
            position: is_int($raw['position'] ?? null) ? $raw['position'] : 0,
            content: $content,
            locales: $locales,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'schema_version' => $this->schemaVersion,
            'position' => $this->position,
            'content' => $this->content,
            'locales' => $this->locales,
        ];
    }

    /**
     * Contenu dans une locale donnée, avec repli sur la langue par défaut.
     *
     * Le repli n'est pas une traduction : c'est le comportement attendu d'une
     * page publique — mieux vaut afficher le français que rien du tout sur une
     * page arabe incomplète.
     *
     * @return array<string, mixed>
     */
    public function contentFor(string $locale): array
    {
        return $this->locales[$locale] ?? $this->content;
    }
}
