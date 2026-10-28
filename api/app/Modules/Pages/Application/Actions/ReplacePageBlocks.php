<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Blocks\PageBlock;
use App\Modules\Pages\Domain\Blocks\PageBlockCollection;
use App\Modules\Pages\Domain\Blocks\PageBlockRegistry;
use App\Modules\Pages\Domain\Blocks\PageBlockSchemaValidator;
use App\Modules\Pages\Domain\Exceptions\PageBlockValidationException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use App\Shared\Contracts\Persistence\TransactionRunner;

/**
 * Remplace l'intégralité des blocs d'une Page (BC PAGES, #8728).
 *
 * ## Pourquoi un remplacement complet et pas un PATCH par bloc
 *
 * L'éditeur envoie la liste ordonnée qu'il affiche. Un PATCH par bloc
 * obligerait le client à rejouer les suppressions, les insertions et les
 * réordonnancements dans le bon ordre — et un client interrompu à mi-chemin
 * laisserait une Page dans un état que personne n'a jamais vu. Le remplacement
 * est atomique : soit la nouvelle liste est valide et devient la Page, soit
 * rien ne change.
 *
 * ## Validation AVANT écriture, jamais après
 *
 * `PageBlockCollection::validate()` rapporte toutes les erreurs indexées par
 * position. On refuse l'écriture entière s'il en existe une seule : stocker un
 * contenu invalide « pour que l'utilisateur puisse le corriger plus tard »
 * ferait entrer dans la base un contenu que le rendu public devra ensuite
 * omettre silencieusement.
 *
 * ## Positions normalisées
 *
 * Les positions sont renumérotées 0..n-1 avant écriture. Sans cela, un échange
 * de deux blocs laisserait un trou, et l'index unique
 * `page_blocks_page_position_unique` refuserait l'écriture au milieu de la
 * transaction.
 */
final class ReplacePageBlocks
{
    public function __construct(
        private readonly PageBlockRegistry $registry,
        private readonly PageBlockSchemaValidator $validator,
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rawBlocks  Forme persistée (cf. `PageBlock::toArray()`)
     *
     * @throws PageBlockValidationException
     */
    public function execute(Page $page, array $rawBlocks): Page
    {
        $collection = PageBlockCollection::fromArray($rawBlocks)->normalized();

        $errors = $collection->validate($this->registry, $this->validator);

        if ($errors !== []) {
            throw PageBlockValidationException::withErrors($errors);
        }

        $this->transactions->run(function () use ($page, $collection): void {
            // Suppression puis réinsertion : l'index unique (page_id, position)
            // interdirait un UPDATE qui déplace un bloc vers une position encore
            // occupée. La transaction rend l'opération atomique du point de vue
            // des lecteurs.
            PageBlockModel::query()->where('page_id', $page->id)->delete();

            foreach ($collection->blocks as $block) {
                /** @var PageBlock $block */
                PageBlockModel::query()->create([
                    'company_id' => $page->company_id,
                    'page_id' => $page->id,
                    'block_uid' => $block->id,
                    'type' => $block->type,
                    'schema_version' => $block->schemaVersion,
                    'position' => $block->position,
                    'content' => $block->content,
                    'locales' => $block->locales === [] ? null : $block->locales,
                ]);
            }
        });

        return $page;
    }
}
