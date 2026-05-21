<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property bool $app_enabled
 * @property bool $email_enabled
 * @property bool $push_enabled
 * @property bool $sms_enabled
 * @property bool $whatsapp_enabled
 * @property string|null $locale
 * @property string|null $timezone
 * @property array<mixed>|null $categories
 * @property array<mixed>|null $quiet_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationPreference extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'app_enabled',
        'email_enabled',
        'push_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'locale',
        'timezone',
        'categories',
        'quiet_hours',
    ];

    protected $casts = [
        'app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'categories' => 'array',
        'quiet_hours' => 'array',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
