<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'sector',
        'country',
        'city',
        'email',
        'plan_id',
        'schema_name',
        'tenancy_type',
        'status',
        'subscription_start',
        'subscription_end',
        'language',
        'timezone',
        'currency',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }
}
