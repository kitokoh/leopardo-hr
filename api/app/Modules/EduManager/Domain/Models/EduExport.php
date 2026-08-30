<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des exports CSV — Issue #5833 (EDU-017).
 *
 * Trace non altérable : qui a exporté quoi, quand, combien de lignes.
 *
 * @property int $id
 * @property string $company_id
 * @property string $kind
 * @property string $filename
 * @property int $record_count
 * @property int|null $exported_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduExport extends Model
{
    use BelongsToCompany;

    public const KIND_STUDENTS = 'students';

    public const KIND_PRESENCE = 'presence';

    public const KIND_GRADES = 'grades';

    public const KINDS = [
        self::KIND_STUDENTS,
        self::KIND_PRESENCE,
        self::KIND_GRADES,
    ];

    protected $table = 'edu_exports';

    protected $fillable = [
        'company_id',
        'kind',
        'filename',
        'record_count',
        'exported_by',
    ];

    protected $casts = [
        'kind' => 'string',
        'record_count' => 'integer',
        'exported_by' => 'integer',
    ];
}
