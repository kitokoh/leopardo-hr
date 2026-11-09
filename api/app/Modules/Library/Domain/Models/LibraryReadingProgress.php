<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Progression de lecture d'un employé sur une ressource de la bibliothèque
 * (#9242).
 *
 * Tenant-scoped via `BelongsToCompany` : la progression d'un employé n'est
 * jamais visible d'un autre tenant. Une ligne par couple
 * (employé, ressource) — la ressource est référencée par SLUG, car le
 * catalogue public Leopardo n'est pas tenant-scoped et une ressource peut être
 * dépubliée sans perdre la position de lecture.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $resource_slug
 * @property int $page
 * @property int $total_pages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryReadingProgress extends Model
{
    use BelongsToCompany;

    protected $table = 'library_reading_progress';

    protected $fillable = [
        'company_id',
        'employee_id',
        'resource_slug',
        'page',
        'total_pages',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'total_pages' => 'integer',
        ];
    }
}
