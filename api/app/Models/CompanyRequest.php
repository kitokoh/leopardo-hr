<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRequest extends Model
{
    protected $table = 'company_requests';

    protected $fillable = [
        'user_id',
        'company_name',
        'sector',
        'country',
        'city',
        'email',
        'phone',
        'description',
        'status',
        'approved_company_id',
        'admin_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'approved_company_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
