<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * PA2-MKT-007 — Marketing acquisition lead (signup, demo_request,
 * newsletter, contact). Global model: lives in the `public` schema, not
 * tenant-scoped, since a lead does not belong to a company yet.
 *
 * @property int $id
 * @property string $external_id
 * @property string $type
 * @property string $email
 * @property string $locale
 * @property string|null $country
 * @property string|null $page
 * @property string|null $source
 * @property string|null $campaign
 * @property string|null $ip
 * @property string|null $referrer
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property string|null $note
 * @property string|null $converted_company_id
 * @property bool $crm_forwarded
 * @property bool $email_forwarded
 * @property Carbon|null $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingLead extends Model
{
    protected $table = 'marketing_leads';

    public const TYPE_SIGNUP = 'signup';

    public const TYPE_DEMO_REQUEST = 'demo_request';

    public const TYPE_NEWSLETTER = 'newsletter';

    public const TYPE_CONTACT = 'contact';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'external_id',
        'type',
        'email',
        'locale',
        'country',
        'page',
        'source',
        'campaign',
        'ip',
        'referrer',
        'payload',
        'status',
        'note',
        'converted_company_id',
        'crm_forwarded',
        'email_forwarded',
        'captured_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'crm_forwarded' => 'boolean',
            'email_forwarded' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    public function markConverted(string $companyId): void
    {
        $this->status = self::STATUS_CONVERTED;
        $this->converted_company_id = $companyId;
    }
}
