<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compte professionnel B2B lié à une station (FUEL-016, issue #5810).
 *
 * Entreprises clientes, flottes, contrats. Contact CHIFFRÉ (RGPD) et
 * consentements marketing explicites par canal. Jamais de lecture des leads
 * du CRM commercial Leopardo (isolation dual-context, ADR-CRM).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $code
 * @property string $name
 * @property string|null $industry
 * @property string|null $contact_encrypted
 * @property array<string, mixed>|null $consents
 * @property string $status active|inactive|archived
 * @property string|null $external_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelProfessionalAccount extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_professional_accounts';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    /** Canaux de consentement marketing. */
    public const CONSENT_CHANNELS = ['email', 'sms', 'whatsapp', 'call'];

    protected $fillable = [
        'company_id',
        'station_id',
        'code',
        'name',
        'industry',
        'contact_encrypted',
        'consents',
        'status',
        'external_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'contact_encrypted' => 'encrypted',
            'consents' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<FuelStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'station_id');
    }

    /** @return HasMany<FuelAccountVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(FuelAccountVisit::class, 'account_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return array<string, bool> */
    public function consentSummary(): array
    {
        $consents = is_array($this->consents) ? $this->consents : [];

        $summary = [];
        foreach (self::CONSENT_CHANNELS as $channel) {
            $summary[$channel] = (bool) ($consents[$channel] ?? false);
        }

        return $summary;
    }
}
