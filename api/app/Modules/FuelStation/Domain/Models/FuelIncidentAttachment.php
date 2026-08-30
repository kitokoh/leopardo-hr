<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pièce jointe d'un incident FuelStation — FUEL-010 (#5804).
 *
 * Téléversement contrôlé : mime + taille validés à l'application, chemin
 * serveur généré (jamais de chemin client), tenant-scoped (FK composite
 * (incident_id, company_id) → fuel_incidents).
 *
 * @property int $id
 * @property string $company_id
 * @property int $incident_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelIncidentAttachment extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incident_attachments';

    protected $fillable = [
        'company_id',
        'incident_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_id' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }
}
