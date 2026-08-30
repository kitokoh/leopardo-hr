<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCustomerContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contact voyageur + registre de consentement par canal (TRAVEL-415,
 * issue #6067).
 *
 * Règle d'or : AUCUNE notification sans consentement explicite par canal
 * (spéc §8.5). Les consentements sont horodatés (traçabilité RGPD).
 *
 * @property int $id
 * @property string $company_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $email_consent_given
 * @property Carbon|null $email_consent_at
 * @property bool $sms_consent_given
 * @property Carbon|null $sms_consent_at
 * @property bool $whatsapp_consent_given
 * @property Carbon|null $whatsapp_consent_at
 * @property string|null $metadata_json
 *
 * @mixin Builder<static>
 */
class TravelCustomerContact extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCustomerContactFactory> */
    use HasFactory;

    protected $table = 'travel_customer_contacts';

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'email_consent_given',
        'email_consent_at',
        'sms_consent_given',
        'sms_consent_at',
        'whatsapp_consent_given',
        'whatsapp_consent_at',
        'metadata_json',
    ];

    protected $casts = [
        'email_consent_given' => 'boolean',
        'email_consent_at' => 'datetime',
        'sms_consent_given' => 'boolean',
        'sms_consent_at' => 'datetime',
        'whatsapp_consent_given' => 'boolean',
        'whatsapp_consent_at' => 'datetime',
    ];

    public function hasEmailConsent(): bool
    {
        return $this->email_consent_given === true && $this->email_consent_at !== null && $this->email !== null;
    }
}
