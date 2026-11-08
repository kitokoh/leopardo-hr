<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Catégorie de contenu de la bibliothèque (administrable, extensible).
 *
 * `company_id` NULL = catégorie du catalogue public Leopardo ; non-NULL =
 * catégorie propre à un tenant.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryCategory extends Model
{
    use BelongsToCompany;

    protected $table = 'library_categories';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'icon',
        'position',
    ];
}
