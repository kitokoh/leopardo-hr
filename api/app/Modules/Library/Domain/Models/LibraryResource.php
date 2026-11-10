<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryDocumentType;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ressource de la bibliothèque (livre, guide, manuel, document…).
 *
 * `company_id` NULL = catalogue public Leopardo ; non-NULL = contenu
 * professionnel privé d'un tenant. Le scope `BelongsToCompany` garantit
 * l'isolation : une ressource `private` n'est jamais servie hors de son tenant.
 *
 * Prix en minor units (entier) + devise ISO 4217 — jamais de flottants.
 * Le fichier est stocké côté objet (`file_key`) : jamais d'URL publique
 * permanente, uniquement des URLs temporaires signées.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $category_id
 * @property int|null $author_id
 * @property int|null $collection_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $excerpt
 * @property LibraryDocumentType $document_type
 * @property string $language
 * @property LibraryAccessModel $access_model
 * @property LibraryResourceStatus $status
 * @property int $price_minor
 * @property string $currency
 * @property array<int, array{min_qty: int, unit_price_minor: int}>|null $wholesale_tiers
 * @property string|null $vat_rate
 * @property string|null $publisher
 * @property int|null $published_year
 * @property int|null $pages
 * @property string $license_id
 * @property string|null $rights_holder
 * @property bool $allow_download
 * @property bool $allow_redistribution
 * @property array<int, string>|null $geo_restrictions
 * @property string|null $file_key
 * @property string|null $file_format
 * @property int|null $file_size
 * @property bool $featured
 * @property bool $is_new
 * @property string|null $created_by
 * @property string|null $published_by
 * @property Carbon|null $published_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryResource extends Model
{
    use BelongsToCompany;

    protected $table = 'library_resources';

    protected $fillable = [
        'company_id',
        'category_id',
        'author_id',
        'collection_id',
        'title',
        'slug',
        'description',
        'excerpt',
        'document_type',
        'language',
        'access_model',
        'status',
        'price_minor',
        'currency',
        'wholesale_tiers',
        'vat_rate',
        'publisher',
        'published_year',
        'pages',
        'license_id',
        'rights_holder',
        'allow_download',
        'allow_redistribution',
        'geo_restrictions',
        'file_key',
        'file_format',
        'file_size',
        'featured',
        'is_new',
        'created_by',
        'published_by',
        'published_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => LibraryDocumentType::class,
            'access_model' => LibraryAccessModel::class,
            'status' => LibraryResourceStatus::class,
            'allow_download' => 'boolean',
            'allow_redistribution' => 'boolean',
            'featured' => 'boolean',
            'is_new' => 'boolean',
            'geo_restrictions' => 'array',
            'wholesale_tiers' => 'array',
            'vat_rate' => 'float',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LibraryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<LibraryAuthor, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(LibraryAuthor::class, 'author_id');
    }

    /**
     * @return BelongsTo<LibraryCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(LibraryCollection::class, 'collection_id');
    }

    /**
     * Vrai si la ressource est visible publiquement (statut publié).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible();
    }
}
