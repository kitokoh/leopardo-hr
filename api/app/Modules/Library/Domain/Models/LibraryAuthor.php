<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Auteur / éditeur d'une ressource de la bibliothèque.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string $slug
 * @property string|null $bio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryAuthor extends Model
{
    use BelongsToCompany;

    protected $table = 'library_authors';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'bio',
    ];
}
