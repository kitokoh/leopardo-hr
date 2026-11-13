<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $job_posting_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $resume_path
 * @property string|null $resume_scan_status
 * @property string|null $resume_scan_engine
 * @property string|null $resume_scan_signature
 * @property Carbon|null $resume_scanned_at
 * @property string|null $cover_letter
 * @property string $source
 * @property string $status
 * @property string|null $rating
 * @property string|null $notes
 * @property Carbon|null $applied_at
 * @property Carbon|null $consent_given_at
 * @property Carbon|null $retention_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Applicant extends Model
{
    use BelongsToCompany;

    protected $table = 'applicants';

    protected $fillable = [
        'company_id',
        'job_posting_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'resume_path',
        'resume_scan_status',
        'resume_scan_engine',
        'resume_scan_signature',
        'resume_scanned_at',
        'cover_letter',
        'source',
        'status',
        'rating',
        'notes',
        'applied_at',
        'consent_given_at',
        'retention_until',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'retention_until' => 'datetime',
        'resume_scanned_at' => 'datetime',
    ];

    /** @return BelongsTo<JobPosting, $this> */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /** @return HasMany<Interview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'applicant_id');
    }

    /**
     * Journal append-only des changements de statut (ATS).
     *
     * @return HasMany<ApplicantStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ApplicantStatusEvent::class, 'applicant_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }
}
