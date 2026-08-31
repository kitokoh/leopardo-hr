<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Relation autorisée responsable légal ↔ élève — Issue #5818 (EDU-002).
 *
 * La table pivot explicite (avec PK propre) porte les droits fins :
 * `can_view_grades`, `can_receive_notifications`. Les FK composites
 * (student_id, company_id) / (guardian_id, company_id) rendent toute
 * relation cross-tenant structurellement impossible.
 *
 * @property int $id
 * @property string $company_id
 * @property int $student_id
 * @property int $guardian_id
 * @property string $relationship_code
 * @property bool $can_view_grades
 * @property bool $can_receive_notifications
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduStudent $student
 * @property-read EduGuardian $guardian
 *
 * @mixin Builder<static>
 */
class EduStudentGuardian extends Model
{
    use BelongsToCompany;

    public const RELATIONSHIP_PARENT = 'parent';

    public const RELATIONSHIP_GUARDIAN = 'guardian';

    public const RELATIONSHIP_OTHER = 'other';

    public const RELATIONSHIPS = [
        self::RELATIONSHIP_PARENT,
        self::RELATIONSHIP_GUARDIAN,
        self::RELATIONSHIP_OTHER,
    ];

    protected $table = 'edu_student_guardians';

    protected $fillable = [
        'company_id',
        'student_id',
        'guardian_id',
        'relationship_code',
        'can_view_grades',
        'can_receive_notifications',
    ];

    protected $casts = [
        'relationship_code' => 'string',
        'can_view_grades' => 'boolean',
        'can_receive_notifications' => 'boolean',
    ];

    /** @return BelongsTo<EduStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /** @return BelongsTo<EduGuardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(EduGuardian::class, 'guardian_id');
    }
}
