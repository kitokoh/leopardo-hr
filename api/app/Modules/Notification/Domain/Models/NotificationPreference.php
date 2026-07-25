<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $whatsapp_consent_given
 * @property Carbon|null $whatsapp_consent_at
 * @property string|null $locale
 * @property string|null $timezone
 * @property array<mixed>|null $categories
 * @property array<mixed>|null $quiet_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
        'whatsapp_consent_given',
        'whatsapp_consent_at',
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
        'whatsapp_consent_given' => 'boolean',
        'whatsapp_consent_at' => 'datetime',
        'categories' => 'array',
        'quiet_hours' => 'array',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * WhatsApp Business messaging (Meta Cloud API policy) requires an
     * explicit, timestamped opt-in per recipient, distinct from the
     * `whatsapp_enabled` channel toggle itself.
     */
    public function hasWhatsappConsent(): bool
    {
        return $this->whatsapp_consent_given === true && $this->whatsapp_consent_at !== null;
    }
}
