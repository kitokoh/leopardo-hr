<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Prescripteur — PHARMA-006 (#7803). Médecin ou professionnel habilité,
 * identifié par son n° d'inscription à l'ordre. Archivage, jamais supprimé
 * (référencé par les ordonnances : traçabilité réglementaire).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $full_name
 * @property string|null $registration_number
 * @property string|null $specialty
 * @property string|null $phone
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyPrescriber extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['active', 'archived'];

    protected $table = 'pharmacy_prescribers';

    protected $fillable = [
        'company_id',
        'full_name',
        'registration_number',
        'specialty',
        'phone',
        'status',
    ];
}
