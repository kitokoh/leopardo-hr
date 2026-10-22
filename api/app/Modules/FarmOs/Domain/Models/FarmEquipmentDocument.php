<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Document associe a un materiel.
 *
 * `file_id` pointe vers le service de fichiers EXISTANT de Leopardo (aucun
 * stockage parallele) ; `external_url` est un lien FOURNI par l'exploitant.
 * Farm OS ne fabrique jamais de lien de telechargement.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_equipment_id
 * @property string $document_type
 * @property string $title
 * @property int|null $file_id
 * @property string|null $external_url
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmEquipmentDocument extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_equipment_documents';

    /** Types de document du registre FERME (extension par nouvelle case). */
    public const TYPES = ['registration', 'insurance', 'invoice', 'manual', 'inspection', 'other'];

    protected $fillable = [
        'company_id',
        'farm_equipment_id',
        'document_type',
        'title',
        'file_id',
        'external_url',
        'issued_on',
        'expires_on',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'expires_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmEquipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(FarmEquipment::class, 'farm_equipment_id');
    }

    /**
     * Un document sans date d'expiration n'est jamais « expire » : on ne
     * suppose rien.
     */
    public function isExpiredAt(?Carbon $at = null): bool
    {
        if ($this->expires_on === null) {
            return false;
        }

        return $this->expires_on->lt(($at ?? Carbon::now())->startOfDay());
    }
}
