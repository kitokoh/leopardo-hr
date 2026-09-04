<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Élève d'un établissement scolaire — Issue #5818 (EDU-002).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 * `display_name` nominative en clair (jamais hors tenant), date de naissance
 * et métadonnées chiffrées au repos (casts `encrypted`).
 *
 * @property int $id
 * @property string $company_id
 * @property string $student_number
 * @property string $display_name
 * @property string|null $birth_date_encrypted
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduStudent extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'edu_students';

    protected $fillable = [
        'company_id',
        'student_number',
        'display_name',
        'birth_date_encrypted',
        'status',
        'metadata',
    ];

    protected $casts = [
        'status' => 'string',
        // PII — chiffré au repos (RGPD / loi 18-07, pattern AccountingContact).
        'birth_date_encrypted' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];
}
