<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal APPEND-ONLY des changements de statut d'une candidature.
 *
 * Une ligne = un changement. On n'édite jamais une ligne existante : c'est ce
 * qui rend l'historique opposable (qui a rejeté ce candidat, et quand ?).
 *
 * @property int $id
 * @property string $company_id
 * @property int $applicant_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string|null $reason
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ApplicantStatusEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'applicant_status_events';

    protected $fillable = [
        'company_id',
        'applicant_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_name',
        'reason',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }
}
