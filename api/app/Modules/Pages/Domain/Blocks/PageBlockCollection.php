<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Une Page = une liste ordonnée de blocs (BC PAGES, #8724).
 *
 * ## Deux opérations, deux régimes d'erreur
 *
 * - **Validation** (`validate()`) retourne TOUTES les erreurs, indexées par
 *   bloc. L'éditeur doit les afficher ensemble : corriger un formulaire champ
 *   par champ, une erreur à la fois, est une expérience inutilisable dès qu'un
 *   contenu contient huit blocs.
 * - **Rendu** (`renderableBlocks()`) n'échoue jamais : un bloc dont le type est
 *   inconnu, dont la version n'est pas supportée ou dont le renderer est absent
 *   est **omis avec un diagnostic**, jamais exécuté comme markup brut. C'est le
 *   critère de sécurité central de l'issue — une page publique ne doit pas
 *   pouvoir casser (ni être détournée) parce qu'un vertical a livré un type
 *   exotique.
 *
 * ## Ordre
 *
 * L'ordre est explicite (`position`), jamais l'ordre d'insertion en base ni
 * l'ordre du tableau JSON : un `UPDATE` qui réordonne les lignes d'une table
 * JSONB ne préserve rien, et deux réponses API pourraient présenter les blocs
 * dans deux ordres différents. Les positions sont normalisées à 0..n-1 avant
 * écriture ({@see self::normalized()}), pour qu'un échange de deux blocs ne
 * laisse pas de trou.
 */
final readonly class PageBlockCollection
{
    /**
     * @param  list<PageBlock>  $blocks
     */
    public function __construct(public array $blocks = []) {}

    /**
     * @param  list<array<string, mixed>>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $blocks = [];
        foreach ($raw as $item) {
            $blocks[] = PageBlock::fromArray($item);
        }

        return new self($blocks);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (PageBlock $b): array => $b->toArray(), $this->blocks);
    }

    /**
     * Blocs triés par position croissante, positions renumérotées 0..n-1.
     *
     * Tri stable : à position égale, l'ordre du tableau d'origine est conservé
     * (`usort` n'est pas garanti stable avant PHP 8.0 ; on trie sur la paire
     * (position, index) pour être déterministe).
     */
    public function normalized(): self
    {
        $indexed = [];
        foreach ($this->blocks as $index => $block) {
            $indexed[] = ['block' => $block, 'position' => $block->position, 'index' => $index];
        }

        usort($indexed, static function (array $a, array $b): int {
            return [$a['position'], $a['index']] <=> [$b['position'], $b['index']];
        });

        $normalized = [];
        foreach ($indexed as $position => $entry) {
            /** @var PageBlock $block */
            $block = $entry['block'];
            $normalized[] = new PageBlock(
                id: $block->id,
                type: $block->type,
                schemaVersion: $block->schemaVersion,
                position: $position,
                content: $block->content,
                locales: $block->locales,
            );
        }

        return new self($normalized);
    }

    /**
     * Valide tous les blocs.
     *
     * @return array<int, list<string>> Erreurs indexées par position de bloc.
     */
    public function validate(PageBlockRegistry $registry, PageBlockSchemaValidator $validator): array
    {
        $errors = [];

        foreach ($this->blocks as $index => $block) {
            $definition = $registry->get($block->type);

            if (! $definition instanceof PageBlockTypeDefinition) {
                $errors[$index][] = sprintf('Type de bloc inconnu : « %s ».', $block->type);

                continue;
            }

            // Version non supportée : on rapporte, on n'essaie JAMAIS de
            // valider un contenu v2 contre un schéma v1 (les messages
            // d'erreur seraient incompréhensibles et le contenu pourrait
            // passer par accident).
            if ($block->schemaVersion !== $definition->schemaVersion) {
                $errors[$index][] = sprintf(
                    'Version de schéma non supportée pour « %s » : bloc en v%d, contrat en v%d.',
                    $block->type,
                    $block->schemaVersion,
                    $definition->schemaVersion,
                );

                continue;
            }

            foreach ($validator->validate($definition, $block->content) as $message) {
                $errors[$index][] = $message;
            }

            foreach ($block->locales as $locale => $payload) {
                foreach ($validator->validate($definition, $payload) as $message) {
                    $errors[$index][] = sprintf('[%s] %s', $locale, $message);
                }
            }
        }

        return $errors;
    }

    /**
     * Blocs réellement rendables, dans l'ordre, avec leurs diagnostics.
     *
     * @return array{blocks: list<PageBlock>, omitted: list<array{type: string, reason: string}>}
     */
    public function renderableBlocks(PageBlockRegistry $registry): array
    {
        $renderable = [];
        $omitted = [];

        foreach ($this->normalized()->blocks as $block) {
            $definition = $registry->get($block->type);

            if (! $definition instanceof PageBlockTypeDefinition) {
                $omitted[] = ['type' => $block->type, 'reason' => 'unknown_type'];

                continue;
            }

            if ($block->schemaVersion !== $definition->schemaVersion) {
                $omitted[] = ['type' => $block->type, 'reason' => 'unsupported_schema_version'];

                continue;
            }

            if (! $definition->isRenderable()) {
                $omitted[] = ['type' => $block->type, 'reason' => 'renderer_missing'];

                continue;
            }

            $renderable[] = $block;
        }

        return ['blocks' => $renderable, 'omitted' => $omitted];
    }
}
