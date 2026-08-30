<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Visite commerciale / opérationnelle d'un compte professionnel
 * (FUEL-016, issue #5810).
 *
 * Append-only et idempotente par external_id (rejeu sans doublon).
 * Notes REDACTED (aucune PII inutile).
 *
 * @property int $id
 * @property string $company_id
 * @property int $account_id
 * @property Carbon $visited_at
 * @property string $purpose
 * @property string|null $notes_redacted
 * @property string|null $external_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelAccountVisit extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_account_visits';

    public const PURPOSE_COMMERCIAL = 'commercial';

    public const PURPOSE_OPERATIONAL = 'operational';

    public const PURPOSE_LOYALTY = 'loyalty';

    public const PURPOSE_OTHER = 'other';

    public const PURPOSES = [
        self::PURPOSE_COMMERCIAL,
        self::PURPOSE_OPERATIONAL,
        self::PURPOSE_LOYALTY,
        self::PURPOSE_OTHER,
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'account_id',
        'visited_at',
        'purpose',
        'notes_redacted',
        'external_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'visited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FuelProfessionalAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FuelProfessionalAccount::class, 'account_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
