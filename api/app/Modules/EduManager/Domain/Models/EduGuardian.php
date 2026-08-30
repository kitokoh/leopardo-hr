<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Responsable légal d'élèves — Issue #5818 (EDU-002).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 * noms en clair (affichage portail), `contact_reference` chiffrée au repos
 * (cast `encrypted`). `verified_at` trace la validation/consentement RGPD.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $contact_reference
 * @property string $relationship_code
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EduStudent> $students
 *
 * @mixin Builder<static>
 */
class EduGuardian extends Model
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

    protected $table = 'edu_guardians';

    protected $fillable = [
        'company_id',
        'employee_id',
        'first_name',
        'last_name',
        'contact_reference',
        'relationship_code',
        'verified_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'relationship_code' => 'string',
        'verified_at' => 'datetime',
        // PII — chiffré au repos (RGPD / loi 18-07, pattern AccountingContact).
        'contact_reference' => 'encrypted',
    ];

    /**
     * Élèves autorisés pour ce responsable (relation explicite).
     *
     * @return BelongsToMany<EduStudent, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(EduStudent::class, 'edu_student_guardians', 'guardian_id', 'student_id')
            ->withPivot('relationship_code', 'can_view_grades', 'can_receive_notifications')
            ->withTimestamps();
    }
}
