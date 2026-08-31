<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCorporateAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compte corporate B2B (TRAVEL-803, issue #6094).
 *
 * Le plafond (`credit_limit_minor`) borne le cumul des réservations
 * corporate ouvertes (pending/confirmed) — vérifié côté serveur à la
 * création de chaque réservation de groupe.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $contact_email
 * @property int $credit_limit_minor
 * @property string $currency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelCorporateAccount extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCorporateAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'contact_email',
        'credit_limit_minor',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'credit_limit_minor' => 'integer',
        'is_active' => 'boolean',
    ];
}
