<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Canal de communication CRM configuré par un tenant (issue #5725/#5727).
 *
 * @property string $id
 * @property string $company_id
 * @property string $type              whatsapp|sms|email
 * @property string $provider          ex. whatsapp_cloud_api|sms_audit
 * @property string $status            active|inactive|error
 * @property bool $is_configured
 * @property int|null $monthly_quota   null = illimité
 * @property int $used_this_month
 * @property string|null $quota_period ex. "2026-08"
 * @property array<string, mixed>|null $settings
 * @property string|null $last_error_message
 * @property Carbon|null $last_error_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmChannel extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_channels';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_configured' => 'boolean',
            'settings' => 'array',
            'last_error_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function isValidType(string $type): bool
    {
        return CrmChannelType::isValid($type);
    }
}
