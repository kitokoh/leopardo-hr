<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ApplicantStatusHistory extends Model
{
    protected $table = 'applicant_status_histories';

    protected $fillable = [
        'applicant_id',
        'from_status',
        'to_status',
        'changed_by',
        'actor_type',
        'note',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }
}
