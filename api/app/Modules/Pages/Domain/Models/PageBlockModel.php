<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bloc de contenu d'une Page (BC PAGES, #8728).
 *
 * ## Pourquoi une ligne par bloc
 *
 * Le contrat de blocs (#8724) porte `position` et `schema_version` PAR bloc :
 * deux blocs d'une même Page peuvent coexister en v1 et v2 pendant une
 * migration de contrat. Une colonne JSONB unique obligerait à réécrire toute
 * la Page pour changer un bloc — donc à perdre les modifications concurrentes
 * de l'autre éditeur.
 *
 * ## Ce que ce modèle ne fait PAS
 *
 * Il ne valide pas le contenu : la validation appartient à
 * {@see \App\Modules\Pages\Domain\Blocks\PageBlockCollection::validate()}, qui
 * rapporte TOUTES les erreurs indexées par bloc. Valider ici, bloc par bloc,
 * donnerait une erreur à la fois — inutilisable dès qu'une Page en contient
 * huit.
 *
 * @property int $id
 * @property string $company_id
 * @property int $page_id
 * @property string $block_uid
 * @property string $type
 * @property int $schema_version
 * @property int $position
 * @property array<string, mixed> $content
 * @property array<string, array<string, mixed>>|null $locales
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page|null $page
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PageBlockModel extends Model
{
    use BelongsToCompany;

    protected $table = 'page_blocks';

    protected $fillable = [
        'company_id',
        'page_id',
        'block_uid',
        'type',
        'schema_version',
        'position',
        'content',
        'locales',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'locales' => 'array',
            'schema_version' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}
