<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Média d'une vitrine publique (BC-27 SHOWCASE, V-MEDIA #6872).
 *
 * Un média appartient à une vitrine (`showcase_id`) et, pour une image de
 * section, à une section précise (`section_id`) — lien par identifiant
 * stable, jamais par chemin absolu côté client. Le binaire vit sur le disk
 * configuré (`disk`, `path`, hors webroot) et n'est servi que par la route
 * publique d'une vitrine publiée (ou de son aperçu à jeton).
 *
 * Tenant-scoped (`company_id`), `showcase_id`/`section_id` sans FK
 * (conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property int $showcase_id
 * @property int|null $section_id
 * @property ShowcaseMediaKind $kind
 * @property string $original_name
 * @property string $file_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CompanyShowcase|null $showcase
 * @property-read CompanyShowcaseSection|null $section
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class ShowcaseMedia extends Model
{
    use BelongsToCompany;

    protected $table = 'showcase_media';

    protected $fillable = [
        'company_id',
        'showcase_id',
        'section_id',
        'kind',
        'original_name',
        'file_name',
        'mime_type',
        'extension',
        'size',
        'disk',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ShowcaseMediaKind::class,
            'showcase_id' => 'integer',
            'section_id' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CompanyShowcase, $this>
     */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(CompanyShowcase::class, 'showcase_id');
    }

    /**
     * @return BelongsTo<CompanyShowcaseSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CompanyShowcaseSection::class, 'section_id');
    }
}
