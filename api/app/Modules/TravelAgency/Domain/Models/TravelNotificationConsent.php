<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Consentement de notification voyageur (TRAVEL-415, issue #6067).
 *
 * Explicitement accordé (guichet/boutique, source tracée) et révocable :
 * un consentement révoqué (`revoked_at`) n'autorise plus aucun envoi sur
 * ce canal pour ce contact.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $contact_identifier
 * @property string $channel
 * @property string $source
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelNotificationConsent extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'contact_identifier',
        'channel',
        'source',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
